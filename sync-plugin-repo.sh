#!/bin/bash

#Check if the correct number of arguments have been provided
if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <WooCommerce plugin SVN repo directory> (See: https://confluence.coinbase-corp.com/pages/viewpage.action?spaceKey=CC&title=Deploying+a+new+version)"
    exit 1
fi

plugin_dir="$1"

if [ ! -d "$plugin_dir" ]; then
    echo "Error: Destination directory does not exist. ('$plugin_dir')"
    exit 1
fi

echo "Updating Plugin Repo..."

cp -R -v ./assets "$plugin_dir"
cp -R -v ./includes "$plugin_dir"
cp -R -v ./vendor "$plugin_dir"
cp -v ./class-wc-gateway-coinbase.php "$plugin_dir"
cp -v ./coinbase-commerce.php "$plugin_dir"
cp -v ./readme.txt "$plugin_dir"

echo "Done!"