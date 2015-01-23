#!/bin/bash

dotfiles_root=$1

sudo=""
if [ `whoami` != 'root' ]
  then
    sudo="sudo"
fi

PKG_OK=$(dpkg-query -W --showformat='${Status}\n' "screen" | grep "install ok installed")
if [ "" == "$PKG_OK" ]; then
  $sudo apt-get install -y screen
fi

if [ -f ~/.screenrc ]; then
    mv ~/.screenrc ~/dotfiles_old/.screenrc
fi
ln -s $dotfiles_root/screen/.screenrc ~/.screenrc

if [ -f ~/.screenrc-startscreens ]; then
    mv ~/.screenrc-startscreens ~/dotfiles_old/.screenrc-startscreens
fi
ln -s $dotfiles_root/screen/.screenrc-startscreens ~/.screenrc-startscreens

if [ -f ~/reload-screens ]; then
    mv ~/reload-screens ~/dotfiles_old/reload-screens
fi
ln -s $dotfiles_root/screen/reload-screens ~/reload-screens

if [ -f ~/start-screens ]; then
    mv ~/start-screens ~/dotfiles_old/start-screens
fi
ln -s $dotfiles_root/screen/start-screens ~/start-screens

echo "Install: done."
