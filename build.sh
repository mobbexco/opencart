#!/bin/sh

VER="2.0.0"

# Remove installed packages
rm -r system/library/mobbex/vendor system/library/mobbex/composer.lock

# Create upload temporal directory
mkdir upload && cp -r admin catalog system ./upload

# Install dependencies
composer install -d upload/system/library/mobbex --no-dev

# Compress files
if type 7z > /dev/null; then
    7z a -tzip "mobbex.$VER.ocmod.zip" upload
elif type zip > /dev/null; then
    zip mobbex.$VER.ocmod.zip -r upload
fi

# Remove upload directory
rm -r upload