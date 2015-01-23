#!/bin/bash

dotfiles_root=$(pwd)
mkdir ~/dotfiles_old

for path in ${dotfiles_root}/*; do
    [ -d "${path}" ] || continue # if not a directory, skip
    dirname="$(basename "${path}")"
    if [ -f ${dirname}/install.sh ]; then
        echo -n "Do you want to install $dirname [y/n]? "
        answer=''
        while [ "$answer" != 'n' ] && [ "$answer" != 'no' ] ; do
            read answer
            answer="$(echo $answer | tr '[:upper:]' '[:lower:]')"
            if [ -z "$answer" ] ; then
	            break
            fi
            if [ "$answer" = 'y' ] || [ "$answer" = 'yes' ] ; then
                echo "Setup dotfiles for $dirname"
                /bin/bash ${dirname}/install.sh ${dotfiles_root}
	            break
            fi
        done
    fi
done
