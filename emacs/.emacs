;;;;;;;;;;;;;
;; Binding ;;
;;;;;;;;;;;;;

(global-set-key [f5]		'revert-buffer)
(global-set-key [(meta g)]	'goto-line)
(global-set-key [(control r)]	'replace-string)

(global-set-key [(control shift up)]	'shrink-window)
(global-set-key [(control shift down)]	'enlarge-window)
(global-set-key [(control shift left)]	'shrink-window-horizontally)
(global-set-key [(control shift right)]	'enlarge-window-horizontally)

;;;;;;;;;;;;;;;;;;;
;; CONFIGURATION ;;
;;;;;;;;;;;;;;;;;;;

(set-language-environment "UTF-8")
(display-time-mode t)

(global-linum-mode 1) ;; line number
(show-paren-mode 1) ; turn on paren match highlighting
(setq show-paren-style 'expression) ; highlight entire bracket expression
(column-number-mode 1) ;;show line number in footer

(add-to-list 'load-path (expand-file-name "~/.emacs.d"))
(require 'sass-mode)

(require 'ido)
(ido-mode t)

;;;;;;;;;;;;;;;
;; YASNIPPET ;;
;;;;;;;;;;;;;;;

(add-to-list 'load-path
              "~/.emacs.d/plugins/yasnippet")
(require 'yasnippet)
(yas-global-mode 1)
(require 'dropdown-list)


;;;;;;;;;;;;;;;;
;; COLORTHEME ;;
;;;;;;;;;;;;;;;;

;; apt-get install emacs-goodies-el

(add-to-list 'load-path "/usr/share/emacs23/site-lisp/emacs-goodies-el/color-theme.el")
(require 'color-theme)
(eval-after-load "color-theme"
  '(progn
     (color-theme-initialize)
     (color-theme-hober)))

(add-to-list 'load-path (expand-file-name "~/.emacs.d/emacs-color-theme-solarized"))
(require 'color-theme-solarized)

(color-theme-solarized-dark)


;;;;;;;;;;;;;;;;;;;;;;;;;
;; FIREFOX AUTOREFRESH ;;
;;;;;;;;;;;;;;;;;;;;;;;;;

(autoload 'moz-minor-mode "moz" "Mozilla Minor and Inferior Mozilla Modes" t)

(add-hook 'javascript-mode-hook 'auto-reload-firefox-on-after-save-hook)
(add-hook 'html-mode-hook 'auto-reload-firefox-on-after-save-hook)
(add-hook 'css-mode-hook 'auto-reload-firefox-on-after-save-hook)
(add-hook 'sass-mode-hook 'auto-reload-firefox-on-after-save-hook)
(add-hook 'php-mode-hook 'auto-reload-firefox-on-after-save-hook)


(defun auto-reload-firefox-on-after-save-hook ()
          (add-hook 'after-save-hook
                       '(lambda ()
                          (interactive)
                          (comint-send-string (inferior-moz-process)
                                              "setTimeout(BrowserReload(), \"1000\");"))
                       'append 'local)) ; buffer-local
