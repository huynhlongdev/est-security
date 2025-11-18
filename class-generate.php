<?php
class Generate_File
{
    public function __construct()
    {
        $this->generate();
        @chmod(ABSPATH . 'wp-config.php', 0400);
        @chmod(ABSPATH . '.htaccess', 0444);
    }

    public function generate()
    {
        // load WP Filesystem
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        global $wp_filesystem;
        $creds = request_filesystem_credentials('', '', false, false, null);
        $files = [];

        if (!WP_Filesystem($creds)) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                return ['success' => false, 'message' => 'WP_Filesystem init failed (permission).'];
            }
        }

        $root_ht_file = ABSPATH . '.htaccess';
        if (!is_writable($root_ht_file)) {
            @chmod($root_ht_file, 0644);
        }

        $default_wp_block = <<<WP
        # BEGIN WordPress
        # The directives (lines) between "BEGIN WordPress" and "END WordPress" are
        # dynamically generated, and should only be modified via WordPress filters.
        # Any changes to the directives between these markers will be overwritten.
        <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
        RewriteBase /
        RewriteRule ^index\.php$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.php [L]
        </IfModule>
        # END WordPress
        WP;

        $insert_content = <<<TXT
        # 1. Disable directory browsing
        Options -Indexes

        # 2. Protect sensitive files
        <FilesMatch "(^\.|wp-config\.php|\.env|composer\.json|composer\.lock|phpunit\.xml)">
            Require all denied
        </FilesMatch>

        # 3. Harden wp-includes
        <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /

        RewriteRule ^wp-admin/includes/ - [F,L]
        RewriteRule !^wp-includes/ - [S=3]
        RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]
        RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]
        RewriteRule ^wp-includes/theme-compat/ - [F,L]
        </IfModule>

        # 4. Disable XML-RPC
        <Files xmlrpc.php>
            Require all denied
        </Files>

        # 5. Prevent author enumeration
        RewriteCond %{QUERY_STRING} author=\d
        RewriteRule ^ - [F]

        # 6. HSTS
        <IfModule mod_headers.c>
        Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        </IfModule>

        # 7. Deny access to .htaccess
        <Files .htaccess>
            Require all denied
        </Files>

        # 8. Prevent access to .git
        RedirectMatch 404 /\.git

        # 9. Block PHP in uploads
        <Directory "/wp-content/uploads">
            <FilesMatch "\.(php|php5|php7|phtml|phar)$">
                Require all denied
            </FilesMatch>
        </Directory>
        TXT;

        $new_ht_content = $default_wp_block . "\n# BEGIN ENOSTA PROTECT\n" . $insert_content . "\n# END ENOSTA PROTECT\n";
        $wp_filesystem->put_contents($root_ht_file, $new_ht_content, FS_CHMOD_FILE);
        @chmod($root_ht_file, 0444);

        // 2) Uploads: block executing php
        $uploads_dir = wp_get_upload_dir();
        $uploads_path = trailingslashit($uploads_dir['basedir']);
        $uploads_ht = <<<HT
        # BEGIN SFCD UPLOADS PROTECT
        <IfModule mod_php7.c>
            <FilesMatch "\.ph(p[3457]?|t|tml)$">
                Require all denied
            </FilesMatch>
        </IfModule>

        <IfModule mod_php.c>
            <FilesMatch "\.ph(p[3457]?|t|tml)$">
                Require all denied
            </FilesMatch>
        </IfModule>

        # Fallback for older servers
        <Files *.php>
            deny from all
        </Files>
        # END SFCD UPLOADS PROTECT
        HT;
        $files[$uploads_path . '.htaccess'] = $uploads_ht;

        // 3) wp-includes: chặn truy cập trực tiếp PHP (an toàn)
        $wp_includes_path = trailingslashit(ABSPATH . WPINC);
        $wp_includes_ht = <<<HT
        # BEGIN SFCD WP-INCLUDES
        <FilesMatch "\.ph(p[3457]?|t|tml)$">
            Require all denied
        </FilesMatch>
        # END SFCD WP-INCLUDES
        HT;
        $files[$wp_includes_path . '.htaccess'] = $wp_includes_ht;

        $errors = [];
        foreach ($files as $fullpath => $content) {
            if (file_exists($fullpath)) {
                continue;
            }

            $dir = dirname($fullpath);
            if (!is_dir($dir)) {
                if (!wp_mkdir_p($dir)) {
                    $errors[] = "Cannot create directory: {$dir}";
                    continue;
                }
            }

            if (file_put_contents($fullpath, $content) === false) {
                $errors[] = "Failed to create: {$fullpath}";
                continue;
            }

            @chmod($fullpath, 0444);
        }
    }
}
