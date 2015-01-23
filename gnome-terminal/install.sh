#!/bin/bash

dotfiles_root=$1

gconftool-2 --load $dotfiles_root/gnome-terminal/gnome-terminal-conf.xml

echo "Install: done."
