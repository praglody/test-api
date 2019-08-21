#! /bin/bash -x

env_bin=$(which env)
env_bin=${env_bin//\//\\\/}
sed -i "s/\/usr\/bin\/env/${env_bin}/" testapi.php
