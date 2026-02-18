#!/bin/bash
set -e

# Check requirements
if [ ! -f "patchbot.phar" ]; then
    echo "Error: patchbot.phar not found. Run build-phar.php first"
    exit 1
fi

if [ ! -f "$HOME/.config/composer/vendor/bin/phpacker" ]; then
    echo "Error: PHPacker not found. Install with: composer global require phpacker/phpacker"
    exit 1
fi

# Read PHP version from .php-version file
PHP_VERSION=$(tr -d '[:space:]' < .php-version)

# Build Linux binary with PHPacker
~/.config/composer/vendor/bin/phpacker build linux x64 --src=./patchbot.phar --dest=./build --php="$PHP_VERSION" --no-interaction

# Clean up
if [ -d "build/linux" ]; then
    [ -f "build/linux/linux-x64" ] && mv build/linux/linux-x64 build/patchbot-linux-x64 && chmod +x build/patchbot-linux-x64
    [ -f "build/linux/linux-arm64" ] && mv build/linux/linux-arm64 build/patchbot-linux-arm64 && chmod +x build/patchbot-linux-arm64
    rmdir build/linux 2>/dev/null || true
fi

echo "Done"
