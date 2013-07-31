#!/bin/sh
shasum index.php > index.php.sha
git commit -a
git push
