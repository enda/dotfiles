#!/bin/bash

dotfiles_root=$1

sudo=""
if [ `whoami` != 'root' ]
  then
    sudo="sudo"
fi

PKG_OK=$(dpkg-query -W --showformat='${Status}\n' "emacs-goodies-el" | grep "install ok installed")
if [ "" == "$PKG_OK" ]; then
  $sudo apt-get install -y emacs-goodies-el
fi

if [ -f ~/.emacs ]; then
    mv ~/.emacs ~/dotfiles_old/.emacs
fi
ln -s $dotfiles_root/emacs/.emacs ~/.emacs

if [ -d ~/.emacs.d ]; then
    mv ~/.emacs.d ~/dotfiles_old/.emacs.d
fi
ln -s $dotfiles_root/emacs/.emacs.d ~/.emacs.d

echo "Install: done."
