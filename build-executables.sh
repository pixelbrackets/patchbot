#!/bin/bash
set -e

# Build all executables (PHAR and binary)

# Install dependencies without dev dependencies
composer install --no-dev --optimize-autoloader

# Build PHAR
php --define phar.readonly=0 build-phar.php
if [ ! -f "patchbot.phar" ]; then
    echo "Error: patchbot.phar was not created"
    exit 1
fi

# Test PHAR
php patchbot.phar list > /dev/null

# Build binary
./build-binary.sh

# Move PHAR to build directory
mv patchbot.phar build/patchbot.phar

# Generate checksums
cd build
sha256sum patchbot-linux-* patchbot.phar > checksums.txt 2>/dev/null || sha256sum patchbot.phar > checksums.txt
cd ..

# Re-Install with dev dependencies for further development
composer install

echo "Done"
