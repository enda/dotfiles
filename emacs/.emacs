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

;; reset key to work with GNU Screen
(global-set-key "\M-[1;5C"    'forward-word)      ; Ctrl+right   => forward word
(global-set-key "\M-[1;5D"    'backward-word)     ; Ctrl+left    => backward word

(global-set-key "\M-[1;5B"    'forward-paragraph)      ; Ctrl+up   => forward p
(global-set-key "\M-[1;5A"    'backward-paragraph)     ; Ctrl+left    => backward p

(global-set-key "\M-[1;6C"    'shrink-window-horizontally)      ; Ctrl+Shift+right   => shrink window
(global-set-key "\M-[1;6D"    'enlarge-window-horizontally)     ; Ctrl+Shift+left    => enlarge window

(global-set-key "\M-[1;2D" 'windmove-left) ; Shift+left => windows left
(global-set-key "\M-[1;2C" 'windmove-right) ; Shift+right => windows right
(global-set-key "\M-[1;2A" 'windmove-up) ; Shift+up => windows up
(global-set-key "\M-[1;2B" 'windmove-down) ; Shift+down => windows down

;; delete trailiong white space before save
(add-hook 'before-save-hook 'delete-trailing-whitespace)

;; delete menu bar, i don't use it.
(menu-bar-mode nil)

;;;;;;;;;;;;;;;;;;;
;; CONFIGURATION ;;
;;;;;;;;;;;;;;;;;;;

(require 'cl)

(set-language-environment "UTF-8")
(display-time-mode t)

(global-linum-mode 1) ;; line number
(show-paren-mode 1) ; turn on paren match highlighting
(setq show-paren-style 'expression) ; highlight entire bracket expression
(column-number-mode 1) ;;show line number in footer

(add-to-list 'load-path (expand-file-name "~/.emacs.d"))
(require 'sass-mode)

(add-to-list 'auto-mode-alist '("\\.tpl$" . html-mode))

;; Disable all version control backends (Start faster if don't use them) :
(setq vc-handled-backends ())

;; column number
(require 'ido)
(ido-mode t)

;;jade-mode
(require 'sws-mode)
(require 'jade-mode)
(add-to-list 'auto-mode-alist '("\\.styl$" . sws-mode))
(add-to-list 'auto-mode-alist '("\\.jade$" . jade-mode))

;;js2-mode
(add-to-list 'load-path "~/.emacs.d/js2-mode")
(autoload 'js2-mode "js2-mode" "JS mode on Emacs" t)
(add-to-list 'auto-mode-alist '("\\.js\\'" . js2-mode))

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


;;;;;;;;;;;;;;;;;;;;;
;; MULTIPLE CURSOR ;;
;;;;;;;;;;;;;;;;;;;;;

;; (require 'multiple-cursors)

;; (global-set-key [(meta m)]	'mc/edit-lines)

;; (global-set-key [(meta >)]			'mc/mark-next-like-this)
;; (global-set-key [(meta <)]			'mc/mark-previous-like-this)
;; (global-set-key [(meta ,))]	'mc/mark-all-like-this)


;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
;; Open a file and go to line ( ne file:XX ) ;;
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

(defun konix/find-file-hook ()
  (if (and
       (string-match "^\\(.+\\):\\([0-9]+\\)$" buffer-file-name)
       (not
        (file-exists-p buffer-file-name)))
      ;; the given file does not exist and is of the form file_name:number, I
      ;; most likely wants to open file_name at line number
      (progn
        (let (
              (old_buffer (current-buffer))
              (file_name (match-string-no-properties 1 buffer-file-name))
              (line (match-string-no-properties 2 buffer-file-name))
              )
          (if (file-exists-p file_name)
              (progn
                (find-file file_name)
                (goto-line (string-to-int line))
                (kill-buffer old_buffer)
                nil)
            nil)))
    nil))
(add-to-list 'find-file-hook 'konix/find-file-hook)

;;;;;;;;;;;;;;;;;;;;;;;;;
;; FIREFOX AUTOREFRESH ;;
;;;;;;;;;;;;;;;;;;;;;;;;;

;; (autoload 'moz-minor-mode "moz" "Mozilla Minor and Inferior Mozilla Modes" t)

;; (add-hook 'javascript-mode-hook 'auto-reload-firefox-on-after-save-hook)
;; (add-hook 'html-mode-hook 'auto-reload-firefox-on-after-save-hook)
;; (add-hook 'css-mode-hook 'auto-reload-firefox-on-after-save-hook)
;; (add-hook 'sass-mode-hook 'auto-reload-firefox-on-after-save-hook)
;; (add-hook 'php-mode-hook 'auto-reload-firefox-on-after-save-hook)


;; (defun auto-reload-firefox-on-after-save-hook ()
;;           (add-hook 'after-save-hook
;;                        '(lambda ()
;;                           (interactive)
;; 			  (rcirc-http-notify-send)
;;                           ;; (comint-send-string (inferior-moz-process)
;;                           ;;                     "setTimeout(BrowserReload(), \"1000\");")
;; 			  )
;;                        'append 'local)) ; buffer-local


;; (defun rcirc-http-notify-send ()
;;   (let ((url-request-method "POST")
;; 	(url-request-extra-headers
;; 	 '(("Content-Type" . "text/plain")))
;; 	(url-request-data "reload"))
;;     (url-retrieve rcirc-http-notify-url 'rcirc-http-notify-kill-url-buffer)))

;; (defun rcirc-http-notify-kill-url-buffer (status)
;;   "Kill the buffer returned by `url-retrieve'."
;;   (kill-buffer (current-buffer)))



;; (setq rcirc-http-notify-url "http://teddy.melty.fr/jerome.musialak.mozrepl")



;; SCSS a la sauce melty
;; (add-to-list 'load-path "~/.emacs.d/scss/")
;; (autoload 'scss-mode "scss-mode" "Mode for editing scss source files")

;; Never use tabs to indent :
(setq-default indent-tabs-mode nil)
(setq-default tab-width 4)
(setq-default py-indent-offset 4)
(setq-default show-trailing-whitespace t)
(setq c-default-style "bsd"
      c-basic-offset 4)
(add-hook 'term-mode-hook
      (lambda() (make-local-variable 'show-trailing-whitespace)
        (setq show-trailing-whitespace nil)))


(defun my-build-tab-stop-list (width)
  (let ((num-tab-stops (/ 80 width))
        (counter 1)
        (ls nil))
    (while (<= counter num-tab-stops)
      (setq ls (cons (* width counter) ls))
      (setq counter (1+ counter)))
    (nreverse ls)))

(add-hook 'c-mode-common-hook
          #'(lambda ()
              ;; You an remove this, if you don't want fixed tab-stop-widths
              (set (make-local-variable 'tab-stop-list)
                   (my-build-tab-stop-list tab-width))
              (setq c-basic-offset tab-width)
              (c-set-offset 'defun-block-intro tab-width)
              (c-set-offset 'arglist-intro tab-width)
              (c-set-offset 'arglist-close 0)
              (c-set-offset 'defun-close 0)
              (setq abbrev-mode nil)))

(defun eeple-indent-style ()
  (interactive)
  (c-set-style "bsd")
  (c-set-offset 'case-label 4)
  (setq c-basic-offset 4))

(add-hook 'php-mode-hook 'eeple-indent-style)
(add-hook 'c-mode-hook 'eeple-indent-style)

;; (setq auto-mode-alist (cons '("\\.scss" . scss-mode) auto-mode-alist))


;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
;; show matching paren when it's off screen ;;
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
(defadvice show-paren-function (after my-echo-paren-matching-line activate)
  "If a matching paren is off-screen, echo the matching line."
  (when (char-equal (char-syntax (char-before (point))) ?\))
    (let ((matching-text (blink-matching-open)))
      (when matching-text
        (message matching-text)))))


;; flymake-cursor (require 'cl)
;; wget http://www.emacswiki.org/emacs/download/flymake-cursor.el
;; aptitude install pyflakes pep8 to check python code
(require 'flymake-cursor nil 'noerror)
(global-set-key "\C-cn" 'flymake-goto-next-error)

(when (load "flymake" t)
  (defun flymake-python-init ()
    (let* ((temp-file (flymake-init-create-temp-buffer-copy
                       'flymake-create-temp-inplace))
           (local-file (file-relative-name temp-file
                        (file-name-directory buffer-file-name))))
      (list "flymake-python" (list local-file))))

   (defun flymake-php-init ()
     (let* ((temp-file (flymake-init-create-temp-buffer-copy
                        'flymake-create-temp-inplace))
            (local-file (file-relative-name temp-file
                         (file-name-directory buffer-file-name))))
       (list "flymake-php" (list local-file))))

  (add-to-list 'flymake-allowed-file-name-masks
               '("\\.py\\'" flymake-python-init))
)

(add-hook 'find-file-hook 'flymake-find-file-hook)
(setq flymake-start-syntax-check-on-find-file nil)

(add-hook 'php-mode-hook 'flymake-mode)

;; XDEBUG

(add-to-list 'load-path "~/.emacs.d/geben")
(add-to-list 'load-path "~/.emacs.d/geben/tree-widget")
(autoload 'geben "geben" "PHP Debugger on Emacs" t)
