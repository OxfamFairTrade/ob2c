cd /home/deb109351/domains/dev.oxfamwereldwinkels.be/public_html:
wp site list --field=url | xargs -i -n1 wp cron event run --due-now --url="{}";