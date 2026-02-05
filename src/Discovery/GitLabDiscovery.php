<?php

namespace Pixelbrackets\Patchbot\Discovery;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Discover repositories from a GitLab group via API
 */
class GitLabDiscovery
{
    private Client $client;
    private string $baseUrl;
    private string $token;

    public function __construct(string $token, string $baseUrl = 'https://gitlab.com')
    {
        $this->token = $token;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'PRIVATE-TOKEN' => $this->token,
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Discover all repositories for a GitLab namespace (auto-detects group vs user)
     *
     * @param string $namespace Namespace path (group path or username)
     * @return array{type: string, repositories: array} Type ('group' or 'user') and repository data
     * @throws GuzzleException
     * @throws \RuntimeException If namespace not found
     */
    public function discover(string $namespace): array
    {
        // Try as group first
        try {
            $repositories = $this->discoverGroup($namespace);
            return ['type' => 'group', 'repositories' => $repositories];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getResponse()->getStatusCode() !== 404) {
                throw $e;
            }
        }

        // Fall back to user
        $repositories = $this->discoverUser($namespace);
        return ['type' => 'user', 'repositories' => $repositories];
    }

    /**
     * Discover all repositories in a GitLab group
     *
     * @param string $groupPath Group path (e.g., "my-org" or "my-org/subgroup")
     * @param bool $includeSubgroups Include projects from subgroups
     * @return array Array of repository data with keys: url, default_branch, name, path_with_namespace
     * @throws GuzzleException
     */
    public function discoverGroup(string $groupPath, bool $includeSubgroups = true): array
    {
        $encodedGroup = urlencode($groupPath);

        return $this->fetchProjects("/api/v4/groups/{$encodedGroup}/projects", [
            'include_subgroups' => $includeSubgroups ? 'true' : 'false',
        ]);
    }

    /**
     * Discover all repositories for a GitLab user
     *
     * @param string $username GitLab username
     * @return array Array of repository data with keys: url, default_branch, name, path_with_namespace
     * @throws GuzzleException
     * @throws \RuntimeException If user not found
     */
    public function discoverUser(string $username): array
    {
        // Look up user ID by username
        $response = $this->client->get('/api/v4/users', [
            'query' => ['username' => $username],
        ]);

        $users = json_decode($response->getBody()->getContents(), true);

        if (empty($users)) {
            throw new \RuntimeException("User '{$username}' not found");
        }

        $userId = $users[0]['id'];

        return $this->fetchProjects("/api/v4/users/{$userId}/projects");
    }

    /**
     * Fetch projects from a GitLab API endpoint with pagination
     *
     * @param string $endpoint API endpoint
     * @param array $extraParams Additional query parameters
     * @return array Array of repository data
     * @throws GuzzleException
     */
    private function fetchProjects(string $endpoint, array $extraParams = []): array
    {
        $repositories = [];
        $page = 1;
        $perPage = 100;

        do {
            $response = $this->client->get($endpoint, [
                'query' => array_merge([
                    'per_page' => $perPage,
                    'page' => $page,
                    'archived' => 'false',
                ], $extraParams),
            ]);

            $projects = json_decode($response->getBody()->getContents(), true);

            if (empty($projects)) {
                break;
            }

            foreach ($projects as $project) {
                $repositories[] = [
                    'name' => $project['name'],
                    'path_with_namespace' => $project['path_with_namespace'],
                    'url' => $project['web_url'],
                    'clone_url_ssh' => $project['ssh_url_to_repo'],
                    'clone_url_http' => $project['http_url_to_repo'],
                    'default_branch' => $project['default_branch'] ?? 'main',
                ];
            }

            $page++;
        } while (count($projects) === $perPage);

        return $repositories;
    }
}
