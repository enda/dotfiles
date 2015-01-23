#!/bin/bash

dotfiles_root=$1

PKG_OK=$(dpkg-query -W --showformat='${Status}\n' "git" | grep "install ok installed")
if [ "" == "$PKG_OK" ]; then
  $sudo apt-get install -y git
fi

if [ -f ~/.gitconfig ]; then
    mv ~/.gitconfig ~/dotfiles_old/.gitconfig
fi
ln -s $dotfiles_root/git/.gitconfig ~/.gitconfig

if [ -f ~/.gituser ]; then
    mv ~/.gituser ~/dotfiles_old/.gituser
fi
ln -s $dotfiles_root/git/.gituser ~/.gituser

echo "Install: done."
