#!/usr/bin/env bash

ROOT_PATH=/var/storage
DIRS=(
    temp
    editorial
)
CONTENT_TYPE=(
    scripts
    outlooks
    advisories
)

CONTENT_FORMAT=(
    html
    xml
    2nsml
    3nsml
)


# Colors.
red='\e[0;31m'
green='\e[0;32m'
cyan='\e[0:36m'
yellow='\e[0;33m'
white='\e[0;37m'
NC='\e[0m'

clear



for DIRECTORY in "${DIRS[@]}"
do
    for TYPE in "${CONTENT_TYPE[@]}"
    do
        for FORMAT in "${CONTENT_FORMAT[@]}"
        do

            echo -en "${white}Making directory ${yellow}${ROOT_PATH}/${DIRECTORY}/${TYPE}/${FORMAT}${NC}"
            mkdir -p ${ROOT_PATH}/${DIRECTORY}/${TYPE}/${FORMAT}
            if [ $? -ne 0 ]; then
                echo -e "${red}FAILED${NC}!"
                exit 1
            fi
            echo -e "${green} DONE${NC}!"

            echo -en "${white}Making directory ${yellow}${ROOT_PATH}/${DIRECTORY}/${TYPE}/${FORMAT}/archive${NC}"
            mkdir -p ${ROOT_PATH}/${DIRECTORY}/${TYPE}/${FORMAT}/archive
            if [ $? -ne 0 ]; then
                echo -e "${red}FAILED${NC}!"
                exit 1
            fi
            echo -e " ${green}DONE${NC}!"

        done
    done
done

echo -en "${white}Setting ownership and permissions...${NC}"
sudo chown -R ec2-user:delivery ${ROOT_PATH}/temp
sudo chown -R ec2-user:editorial ${ROOT_PATH}/editorial
sudo chmod -R 777 ${ROOT_PATH}/temp
sudo chmod -R 750 ${ROOT_PATH}/editorial
echo -e "Done"
