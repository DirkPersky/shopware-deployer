#!/bin/bash

green=`tput setaf 2`
reset=`tput sgr0`

#Project GIT
read -p "Geben sie den Namen des neuen .GIT an:" gitname
echo -e "${green}\u2714${reset} Projekt wird vorbereitet"
git remote add upstream git@github.com:shopware/production.git


# get Shopware
git clone git@github.com:shopware/production.git $name

echo -e "${green}\u2714${reset} .GIT-Repo wird vorbereitet"
# switch directory and remove hisory
cd $name
rm -rf .git
# init new .git
git init
git add --all
git commit -m "Project Start"
git branch -M main

echo -e "${green}\u2714${reset} upload zum .GIT-Repo"
#add new origin
git remote add origin $gitname
git push -u origin main --force
# add remote upstream
git remote add upstream git@github.com:shopware/production.git

echo -e "${green}\u2714${reset} Projekt angelegt"

#echo -e "${green}\u2714${reset} Prepare Remote Server"
#ssh-keygen -t rsa -b 4096 -f $name
#ssh-copy-id -i $name.pub p593040@p593040.mittwaldserver.info
#rm $name.pub
