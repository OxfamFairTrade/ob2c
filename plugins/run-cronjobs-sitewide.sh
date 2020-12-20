cd /home/deb109351/domains/shop.oxfamwereldwinkels.be/public_html;
/home/deb109351/bin/wp site list --field=url | xargs -i -n1 /home/deb109351/bin/wp cron event run --due-now --url="{}"