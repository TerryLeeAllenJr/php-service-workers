#!/usr/bin/env bash


# Colors.
red='\e[0;31m'
green='\e[0;32m'
cyan='\e[0:36m'
yellow='\e[0;33m'
white='\e[0;37m'
NC='\e[0m'

clear
read -p "Installing DarkOS delivery platform. Proceed?" -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    clear
    exit 1
fi

echo -e "${yellow}Creating directory structure...?${NC}"
./createDirectories.sh