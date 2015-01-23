#!/bin/bash

dotfiles_root=$1

sudo=""
if [ `whoami` != 'root' ]
  then
    sudo="sudo"
fi

PKG_OK=$(dpkg-query -W --showformat='${Status}\n' "git" | grep "install ok installed")
if [ "" == "$PKG_OK" ]; then
  $sudo apt-get install -y git
fi

install_zsh () {
    if [ -f /bin/zsh -o -f /usr/bin/zsh ]; then
        if [[ ! -d ~/.oh-my-zsh/ ]]; then
            git clone http://github.com/robbyrussell/oh-my-zsh.git ~/.oh-my-zsh
        fi
        if [[ ! $(echo $SHELL) == $(which zsh) ]]; then
	        echo "$current_user, please type your password to proceed:"
	        chsh -s "$(which zsh | head -1)"
            if [ -f ~/.zshrc ]; then
                mv ~/.zshrc ~/dotfiles_old/.zshrc
            fi
            ln -s $dotfiles_root/zsh/.zshrc ~/.zshrc
            echo "Install: done."
        fi
    else
        $sudo apt-get install -y zsh
        install_zsh
    fi
}

install_zsh
