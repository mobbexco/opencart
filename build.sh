#!/bin/sh

VER="1.0.0"

# Create upload temporal directory
mkdir upload && cp -r admin catalog system ./upload

# Compress files
if type 7z > /dev/null; then
    7z a -tzip "mobbex.$VER.ocmod.zip" upload
elif type zip > /dev/null; then
    zip mobbex.$VER.ocmod.zip -r upload
fi

# Remove upload directory
rm -r upload