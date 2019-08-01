#!/usr/bin/env bash

set -e

SCRIPTDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$SCRIPTDIR";

DEVREPO="git@github.com:moderntribe/bigcommerce-price-cache.git"

branch="master"
version=""

while getopts "b:v:" opt; do
    case "$opt" in
        b)
            branch=$OPTARG
            ;;
        v)
            version=$OPTARG
            ;;
    esac
done

mkdir -p .build
mkdir -p .build/cache

if [ -d .build/src ]; then
    cd .build/src
    git fetch
    cd ../..
else
    git clone $DEVREPO .build/src
fi

cd .build/src
git reset --hard HEAD
git checkout $branch
git reset --hard origin/$branch
git pull origin $branch
commit_hash=$(git rev-parse HEAD)
cd "$SCRIPTDIR"

docker-compose build && \
docker-compose run --rm package /bin/bash -c 'source $HOME/.bashrc \
 && cd /data/src \
 && composer install --classmap-authoritative --no-dev \
 && rsync -rp --delete /data/src/ /data/plugin \
    --include=bigcommerce-price-cache.php \
    --include=*.md \
    --exclude=/.* \
    --exclude=/*.* \
    --exclude=/package \
    --exclude=.git*'


# ensure proper version string is set everywhere
if [ -z "$version" ]; then
	version=$(grep 'const VERSION =' .build/bigcommerce-price-cache/src/BigCommerce_Price_Cache/Plugin.php | awk -F\' '{ print $2 }')
fi
echo "Setting plugin version to $version"
perl -pi -e "s/Version:(\s+)(.*)$/Version:\${1}${version}/g" .build/bigcommerce-price-cache/bigcommerce-price-cache.php
perl -pi -e "s/const VERSION = '.*?';$/const VERSION = '$version';/g" .build/bigcommerce-price-cache/src/BigCommerce_Price_Cache/Plugin.php

cd "$SCRIPTDIR/.build"
zipfile="$SCRIPTDIR/.build/zip/bigcommerce-price-cache-$version.zip"

if [ ! -f ${zipfile} ]; then
    rm -f "$zipfile"
fi
zip -rq9 "$zipfile" bigcommerce-price-cache
echo "Plugin packaged to $zipfile"
