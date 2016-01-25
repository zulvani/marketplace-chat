set CIAPI_HOME=public_html/mchat
ncftpput -f cred.cfg -R %CIAPI_HOME%/static e:\agus\server\marketplace-chat\static\*
ncftpput -f cred.cfg -R %CIAPI_HOME%/libs e:\agus\server\marketplace-chat\libs\*
ncftpput -f cred.cfg -R %CIAPI_HOME% e:\agus\server\marketplace-chat\static\index.php