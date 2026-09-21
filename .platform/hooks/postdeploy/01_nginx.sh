#!/bin/bash
set -e
DIR=/var/proxy/staging/nginx/conf.d/elasticbeanstalk
mkdir -p "$DIR"
cat > "$DIR/zz_laravel.conf" << 'EOF'
if (!-e $request_filename) {
    rewrite ^ /index.php last;
}
EOF
/usr/sbin/nginx -t -c /var/proxy/staging/nginx/nginx.conf
cp -rp /var/proxy/staging/nginx/* /etc/nginx
systemctl reload nginx
