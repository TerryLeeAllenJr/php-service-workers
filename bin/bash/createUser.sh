#!/usr/bin/env bash

PATH=/var/bin/bash/
USER=$1

# Create User
clear
echo "Creating ${USER}\n"
adduser ${USER};

# Create User password and store it in a file
echo "Generating password...\n"

PASS=$(setPasswd);
echo ${PASS} | passwd foo --stdin




exit





# Add User to editorial group


#


function createNewUser(){
 echo true
}


function setPasswd()
{
    < /dev/urandom tr -dc _A-Z-a-z-0-9 | head -c${1:-16};echo;
}




