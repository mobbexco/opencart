#!/bin/sh

VER="1.0.0"

if type 7z > /dev/null; then
    7z a -tzip "mobbex.$VER.oc.zip" admin catalog system
elif type zip > /dev/null; then
    zip mobbex.$VER.oc.zip -r admin catalog system
fi