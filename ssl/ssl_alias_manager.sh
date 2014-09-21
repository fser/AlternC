#!/bin/bash

rm -f /var/run/alternc/ssl/generate_certif_alias

# Launched by incron when /tmp/generate_certif_alias exists
# regenerate the list of global aliases used by Comodo for certificate ownership validation
# FIXME: how do we lock that, ensuring we don't launch this more than once ?
APACHECONF=/etc/apache2/conf.d/alternc-ssl_cert-alias.conf
TMP=/tmp/alternc-ssl_cert-alias_${$}.tmp
FILEDIR=/var/lib/alternc/ssl-cert-alias


rm -f "$TMP"
mkdir -p "$FILEDIR"

mysql --defaults-file=/etc/alternc/my.cnf --skip-column-names -B -e "SELECT name,content FROM certif_alias;" | while read name content
do
    echo "alias $name ${FILEDIR}${name}" >>$TMP
    echo "$content" >"${FILEDIR}${name}"
done
mv -f "$TMP" "$APACHECONF"

service apache2 reload

