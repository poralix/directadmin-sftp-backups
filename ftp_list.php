#!/bin/sh
# ===========================================================
# Original script was written by Directadmin
# ===========================================================
# Patched:
#  sFTP/SSH support added
#  By Alex Grebenschikov, Poralix, www.poralix.com
#  Last modified: Wed Dec 11 20:30:29 +07 2019
#  Version: 1.2.poralix.3 $ Thu Dec  7 18:54:17 +07 2023
#           1.2.poralix.2 $ Wed Dec 11 20:30:29 +07 2019
#           1.2.poralix   $ Thu Sep 12 23:55:18 +07 2019
# ===========================================================

FTPLS=/usr/bin/ncftpls
CURL=/usr/local/bin/curl
if [ ! -e ${CURL} ]; then
	CURL=/usr/bin/curl
fi
TMPDIR=/home/tmp
PORT=${ftp_port}
FTPS=0
if [ "${ftp_secure}" = "ftps" ]; then
	FTPS=1
fi

SSL_REQD=""
if ${CURL} --help | grep -m1 -q 'ftp-ssl-reqd'; then
    SSL_REQD="--ftp-ssl-reqd"
elif ${CURL} --help | grep -m1 -q 'ssl-reqd'; then
    SSL_REQD="--ssl-reqd"
fi

if [ "$PORT" = "" ]; then
	PORT=21
fi

RANDNUM=`/usr/local/bin/php -r 'echo rand(0,10000);'`
#we need some level of uniqueness, this is an unlikely fallback.
if [ "$RANDNUM" = "" ]; then
        RANDNUM=$ftp_ip;
fi

CFG=$TMPDIR/$RANDNUM.cfg
rm -f $CFG
touch $CFG
chmod 600 $CFG

DUMP=$TMPDIR/$RANDNUM.dump
rm -f $DUMP
touch $DUMP
chmod 600 $DUMP

# Poralix
SSH_SFTP="/usr/bin/sftp";
[ -x "${SSH_SFTP}" ] || SSH_SFTP="/bin/sftp";
SSHPASS="/usr/bin/sshpass";
[ -x "${SSHPASS}" ] || SSHPASS="/bin/sshpass";
USE_SSH=0;
SSH_PORTS="22 2200 22022";
SSH_KEY="";
for SSH_PORT in ${SSH_PORTS};
do
	# Working with SSH?
	if [ -n "${PORT}" ] && [ "${PORT}" = "${SSH_PORT}" ]; then
		USE_SSH=1;
		break;
	fi;
done;

list_files_ssh()
{
	# All the binaries installed?
	if [ ! -x "${SSH_SFTP}" ] || [ ! -x "${SSHPASS}" ]; then
		echo "";
		echo "*** Backup not uploaded ***";
		echo "Can not find SFTP and/or SSHPASS binaries";
		echo "";
		echo "Please install sftp and/or sshpass using OS repository";
		echo "";
		exit 10;
	fi;

	# Use SSH KEY?
	if [ -n "${ftp_password}" ] && [ -f "${ftp_password}" ]; then
		ssh-keygen -P '' -e -y -f "${ftp_password}" >/dev/null 2>&1;
		if [ "$?" = "0" ]; then
			SSH_KEY="${ftp_password}";
		fi;
	fi;

	TMP_BATCH_FILE=$(mktemp);
	#echo "progress" >> ${TMP_BATCH_FILE};
	echo "cd ${ftp_path}" >> ${TMP_BATCH_FILE};
	echo "ls -1" >> ${TMP_BATCH_FILE};
	echo "quit" >> ${TMP_BATCH_FILE};

	if [ -z "${SSH_KEY}" ]; then
		echo "${ftp_password}" >> ${CFG};
		${SSHPASS} -f${CFG} ${SSH_SFTP} -C -oBatchMode=no -b ${TMP_BATCH_FILE} -oUserKnownHostsFile=/dev/null -oStrictHostKeyChecking=no -oPort=${PORT} ${ftp_username}@${ftp_ip} > ${DUMP} 2>&1;
	else
		${SSH_SFTP} -C -oBatchMode=no -b ${TMP_BATCH_FILE} -oUserKnownHostsFile=/dev/null -oStrictHostKeyChecking=no -oPort=${PORT} -oIdentityFile=${SSH_KEY} ${ftp_username}@${ftp_ip} > ${DUMP} 2>&1;
	fi;
	RET=$?;

	[ -f "${TMP_BATCH_FILE}" ] && rm -f ${TMP_BATCH_FILE};

	if [ "${RET}" -ne 0 ]; then
		echo "[list] sftp return code: $RET";
		cat ${DUMP};
	else
		# Backup file names might be of the following format:
		# =======================================================================
		# zstd=1 & backup_gzip=2 & encryption=0 => admin.root.admin.tar.zst
		# zstd=0 & backup_gzip=1 & encryption=0 => admin.root.admin.tar.gz
		# zstd=0 & backup_gzip=0 & encryption=0 => admin.root.admin.tar
		#
		# zstd=1 & backup_gzip=2 & encryption=1 => admin.root.admin.tar.zst.enc
		# zstd=0 & backup_gzip=1 & encryption=1 => admin.root.admin.tar.gz.enc
		# zstd=0 & backup_gzip=0 & encryption=1 => admin.root.admin.tar.enc
		#
		cat ${DUMP} | grep -v '^sftp> ' | grep -E '(.*)\.(tar)(|\.gz|\.zst)(|\.enc)$';
	fi
}
# Poralix

#######################################################
# FTP
list_files()
{
	if [ ! -e $FTPLS ]; then
		echo "";
		echo "*** Unable to get list ***";
		echo "Please install $FTPLS by running:";
		echo "";
		echo "cd /usr/local/directadmin/scripts";
		echo "./ncftp.sh";
		echo "";
		exit 10;
	fi

	#man ncftpls lists:
	#If you want to use absolute pathnames, you need to include a literal slash, using the "%2F" code for a "/" character.
	#use expr to replace /path to /%2Fpath, if needed.
	CHAR1=`echo ${ftp_path} | awk '{print substr($1,1,1)}'`
	if [ "$CHAR1" = "/" ]; then
		new_path="/%2F`echo ${ftp_path} | awk '{print substr($1,1)}'`"
		ftp_path=${new_path}
	else
		ftp_path="/${ftp_path}"
	fi

	echo "host $ftp_ip" >> $CFG
	echo "user $ftp_username" >> $CFG
	echo "pass $ftp_password" >> $CFG

	if [ ! -s $CFG ]; then
		echo "ftp config file $CFG is 0 bytes.  Make sure $TMPDIR is chmod 1777 and that this is enough disk space.";
		echo "running as: `id`";
		df -h
		exit 11;
	fi

	$FTPLS -l -f $CFG -P ${PORT} -r 1 -t 10 "ftp://${ftp_ip}${ftp_path}" > $DUMP 2>&1
	RET=$?

	if [ "$RET" -ne 0 ]; then
		cat $DUMP

		if [ "$RET" -eq 3 ]; then
			echo "Transfer failed. Check the path value. (error=$RET)";
		else
			echo "${FTPLS} returned error code $RET";
		fi

	else
		COLS=`awk '{print NF; exit}' $DUMP`
		cat $DUMP | grep -v -e '^d' | awk "{ print \$${COLS}; }"
	fi
}

#######################################################
# FTPS
list_files_ftps()
{
	if [ ! -e ${CURL} ]; then
		echo "";
		echo "*** Unable to get list ***";
		echo "Please install curl by running:";
		echo "";
		echo "cd /usr/local/directadmin/custombuild";
		echo "./build curl";
		echo "";
		exit 10;
	fi

	#double leading slash required, because the first one doesn't count.
	#2nd leading slash makes the path absolute, in case the login is not chrooted.
	#without double forward slashes, the path is relative to the login location, which might not be correct.
	ftp_path="/${ftp_path}"

	/bin/echo "user =  \"$ftp_username:$ftp_password\"" >> $CFG

	${CURL} --config ${CFG} ${SSL_REQD} -k --silent --show-error ftp://$ftp_ip:${PORT}$ftp_path/ > ${DUMP} 2>&1
	RET=$?

	if [ "$RET" -ne 0 ]; then
		echo "${CURL} returned error code $RET";
		cat $DUMP
	else
		COLS=`awk '{print NF; exit}' $DUMP`
		cat $DUMP | grep -v -e '^d' | awk "{ print \$${COLS}; }"
	fi
}


#######################################################
# Start

if [ "${USE_SSH}" = "1" ]; then
	list_files_ssh;
else
	if [ "${FTPS}" = "1" ]; then
		list_files_ftps
	else
		list_files
	fi
fi;

rm -f $CFG
rm -f $DUMP

exit $RET
