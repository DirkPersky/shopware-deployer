#!/bin/bash

green=`tput setaf 2`
yellow=`tput setaf 3`
reset=`tput sgr0`

read -p "${yellow}?${reset} Geben sie den Source Folder an: " foldername

cp $foldername/.env ../../shared
cp -R $foldername/custom/plugins ../../shared/custom
cp -R $foldername/config/jwt ../../shared/config
cp -R $foldername/config/packages ../../shared/config
cp -R $foldername/files ../../shared
cp -R $foldername/var/log ../../shared/var
cp -R $foldername/public/media ../../shared/public
cp -R $foldername/public/thumbnail ../../shared/public
cp -R $foldername/public/sitemap ../../shared/public
cp -R $foldername/public/bundles ../../shared/public