#!/bin/bash


helpers_dir=$(dirname $(dirname $(readlink -f $0)))
echo "helpers_dir $helpers_dir"

if [ ! -e "vendor/w4os/w4os-helpers" ]
then
  echo "This script must be run from a project using w4os/w4os-helpers as a library"
  exit 0
fi

rsync --delete -Wavz "$helpers_dir/" helpers/ --exclude-from=.gitignore --exclude=$0 --exclude-from="$helpers_dir/.gitignore" --exclude-from="$helpers_dir/.libignore"
