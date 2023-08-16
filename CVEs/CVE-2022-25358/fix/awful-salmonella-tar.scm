(module awful-salmonella-tar

  (awful-salmonella-tar
   cache-dir
   salmonella-reports-dir
   salmonella-report-dir
   report-tar-filename
   report-compressor
   report-tar-contains-compressed-files?)

(import scheme)
(cond-expand
  (chicken-4
   (import chicken)
   ;; Core units
   (use data-structures extras files irregex posix srfi-1 srfi-13 utils)
   ;; Eggs
   (use awful intarweb spiffy))
  (chicken-5
   (import (chicken base)
           (chicken condition)
           (chicken errno)
           (chicken file)
           (chicken file posix)
           (chicken format)
           (chicken irregex)
           (chicken pathname)
           (chicken process)
           (chicken string)
           (chicken sort)
           (chicken time posix))
   (import awful intarweb spiffy srfi-1 srfi-13)
   (define (file-read-access? file)
     (handle-exceptions exn
       (if (= (errno) errno/noent)
           #f
           (abort exn))
       (file-readable? file))))
  (else
   (error "Unsupported CHICKEN version.")))


(define cache-dir (make-parameter "cache"))

(define salmonella-reports-dir (make-parameter ""))

(define salmonella-report-dir (make-parameter "salmonella-report"))

(define report-tar-filename (make-parameter "salmonella-report.tar.gz"))

(define report-compressor
  ;; #f specifies no compression (plain tar file)
  (make-parameter 'gzip
                  (lambda (v)
                    (if (or (not v)
                            (member v '(gzip bzip2)))
                        v
                        (error 'report-compressor "Unsupported compressor" v)))))

(define report-tar-contains-compressed-files?
  (make-parameter #f))

(define (index-file)
  (if (report-tar-contains-compressed-files?)
      "index.htmlz"
      "index.html"))

(define (path-join parts)
  (string-intersperse parts "/"))

(define (path-split path)
  (string-split path "/"))

(define safe-path?
  ;; Just in case, since we are using the external program tar
  (let ((safe-chars
         (string->list "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-+/:._")))
    (lambda (path)
      (and (not (substring-index ".." path))
           (let ((path-chars (string->list path)))
             (every (lambda (path-char)
                      (memq path-char safe-chars))
                    path-chars))))))

(define (requested-path->tar-file requested-file-path)
  (define (tar-file-path path-parts)
    (make-pathname (list (root-path)
                         (salmonella-reports-dir)
                         (path-join path-parts))
                   (report-tar-filename)))
  (let ((path-parts (path-split requested-file-path)))
    (if (or (null? path-parts)
            (null? (cdr path-parts))
            (not (safe-path? requested-file-path)))
        #f
        (cond ((equal? (index-file) (last path-parts))
               (tar-file-path (drop-right path-parts 2)))
              ((equal? (salmonella-report-dir) (last path-parts))
               (tar-file-path (butlast path-parts)))
              (else
               (let ((dir-part (last (butlast path-parts))))
                 (case (string->symbol dir-part)
                   ((salmonella-report)
                    (tar-file-path (butlast path-parts)))
                   ((dep-graphs install ranks rev-dep-graphs test)
                    (tar-file-path (drop-right path-parts 3)))
                   (else #f))))))))

(define (split-by-salmonella-report requested-file-path)
  ;; Given /<branch>/<c-compiler>/<os>/<arch>/<yyyy>/<mm>/<dd>/salmonella-report/<report-path>,
  ;; return (/<branch>/<c-compiler>/<os>/<arch>/<yyyy>/<mm>/<dd> . salmonella-report/<report-path>)
  (let ((parts (path-split requested-file-path)))
    (let-values (((pre post) (split-at parts
                                       (list-index (lambda (elt)
                                                     (equal? elt (salmonella-report-dir)))
                                                   parts))))
      (cons (path-join pre)
            (path-join post)))))

(define (tar-get requested-file-path)
  ;; FIXME: this code is subject to race conditions
  (let ((cache-file (make-pathname (cache-dir) requested-file-path)))
    (if (file-read-access? cache-file)
        cache-file
        (and-let* ((tar-file (requested-path->tar-file requested-file-path))
                   (pre/post (split-by-salmonella-report requested-file-path))
                   (pre (car pre/post))
                   (post (cdr pre/post)))
          (and (file-exists? tar-file)
               (handle-exceptions exn
                 #f
                 (let* ((out-dir (make-pathname (cache-dir) pre))
                        ;; Ugly.  Maybe use some tar implementation in
                        ;; scheme (e.g., snowtar, or port the tar egg to
                        ;; chicken 4)
                        (cmd (sprintf "tar x~af ~a -C ~a ~a"
                                      (case (report-compressor)
                                        ((gzip) "z")
                                        ((bzip2) "j")
                                        (else ""))
                                      tar-file
                                      out-dir
                                      (qs post))))
                   (create-directory out-dir 'with-parents)
                   (system* cmd)
                   (make-pathname out-dir post))))))))

;; As I understand it, configuring mime-type-map shouldn't be
;; necessary.  However, it seems that (content-type #(text/html
;; ((charset . utf-8)))) is not being set by with-headers in
;; send-gzipped-file
(mime-type-map
 (append
  (mime-type-map)
  '(("logz"  . text/plain)
    ("svg"   . image/svg+xml)
    ("svgz"  . image/svg+xml)
    ("htmlz" . text/html))))

(define (send-gzipped-file file)
  (if (memq 'gzip (header-values 'accept-encoding
                                 (request-headers (current-request))))
        (with-headers '((content-type #(text/html ((charset . utf-8))))
                        (content-encoding gzip))
           (lambda ()
             (send-static-file file)))
      (send-response
       code: 406
       body: "<h1>406 - Only gzip-compressed content is available</h1>")))

(define (send-file file)
  (if (string-suffix? "z" file)
      (send-gzipped-file file)
      (send-static-file file)))

(define (send-file-from-cache file-path)
  (parameterize ((root-path (pathname-directory file-path)))
    (let ((filename (pathname-strip-directory file-path)))
      (send-file filename))))


(define (render-dir web-dir fs-dir)
  `(div (@ (id "content"))
        (h2 "Index of " (code ,web-dir) ":")
        (p (a (@ (href ,(pathname-directory web-dir)))
              "Go to parent directory"))
        ,(let ((dir-content (directory fs-dir)))
           `(table
             ,@(map (lambda (f)
                      (let* ((fs-file (make-pathname fs-dir f))
                             (salmonella-report-dir?
                              (equal? f (salmonella-report-dir)))
                             (dir? (or salmonella-report-dir?
                                       (directory? fs-file))))
                        `(tr
                          (td (a (@ (href ,(make-pathname web-dir f)))
                                 ,(if dir?
                                      (string-append f "/")
                                      f)))
                          (td ,(if salmonella-report-dir?
                                   "---"
                                   (file-size fs-file)))
                          (td ,(if salmonella-report-dir?
                                   "---"
                                   (seconds->string (file-modification-time fs-file)))))))
                    (sort
                     (if (member (report-tar-filename) dir-content)
                         (cons (salmonella-report-dir) dir-content)
                         dir-content)
                     string<))))))

(define (default-app-settings handler)
  (parameterize ((page-css "//wiki.call-cc.org/chicken.css"))
    (handler)))

(define (awful-salmonella-tar base-path #!key (awful-settings default-app-settings))

  (define base-path-pattern
    (irregex (string-append (string-chomp base-path "/") "(/.*)*")))

  (define-app awful-salmonella-tar
    matcher: (lambda (path)
               (irregex-match base-path-pattern path))
    handler-hook: (lambda (handler)
                    (parameterize ((app-root-path base-path))
                      (awful-settings handler)))

    (create-directory (cache-dir) 'with-parents)

    (define-page (irregex (string-append base-path ".*"))
      (lambda (req-path)
        (let ((fs-path (make-pathname (list (root-path)
                                            (salmonella-reports-dir))
                                      req-path))
              (not-found (lambda ()
                           (send-status 'not-found))))
          (cond ((not (safe-path? req-path))
                 (lambda ()
                   (not-found)))
                ((directory? fs-path)
                 (render-dir req-path fs-path))
                (else
                 (lambda ()
                   (handle-exceptions exn
                     (not-found)
                     (cond ((equal? (pathname-strip-directory req-path)
                                    (salmonella-report-dir))
                            (redirect-to (string-append req-path "/")))
                           ((equal? (pathname-strip-directory (string-chomp req-path "/"))
                                    (salmonella-report-dir))
                            (cond ((tar-get (make-pathname req-path (index-file)))
                                   => send-file-from-cache)
                                  (else (not-found))))
                           ((tar-get req-path) => send-file-from-cache)
                           (else
                            (if (file-exists? (make-pathname (list (root-path)
                                                                   (salmonella-reports-dir))
                                                             req-path))
                                (parameterize ((root-path (salmonella-reports-dir)))
                                  (send-file req-path))
                                (not-found)))))))))))
    ) ;; end define-app
  ) ;; end awful-salmonella-tar
) ;; end module
