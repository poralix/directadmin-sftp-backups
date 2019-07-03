#!/bin/sh
# ===========================================================
# Original script was written by Directadmin
# ===========================================================
# Patched:
#  sFTP/SSH support added
#  By Alex Grebenschikov, Poralix, www.poralix.com
#  Last modified: Tue Jan 30 12:43:50 +07 2018
#  Version: 0.1.poralix $ Tue Jan 30 12:43:50 +07 2018
# ===========================================================
FTPPUT=/usr/bin/ncftpput
CURL=/usr/local/bin/curl
OS=`uname`;
DU=/usr/bin/du
BC=/usr/bin/bc
EXPR=/usr/bin/expr
TOUCH=/bin/touch
PORT=${ftp_port}
FTPS=0
MD5=${ftp_md5}

if [ "${ftp_secure}" = "ftps" ]; then
	FTPS=1
fi

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

upload_file_ssh()
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
	[ -n "${ftp_path}" ] && echo "-mkdir ${ftp_path}" >> ${TMP_BATCH_FILE};
	echo "cd ${ftp_path}" >> ${TMP_BATCH_FILE};
	echo "put ${ftp_local_file} ${ftp_path}" >> ${TMP_BATCH_FILE};
	echo "quit" >> ${TMP_BATCH_FILE};
	
	if [ -z "${SSH_KEY}" ]; then
		echo "${ftp_password}" >> ${CFG};
		${SSHPASS} -f${CFG} ${SSH_SFTP} -C -oBatchMode=no -b ${TMP_BATCH_FILE} -oUserKnownHostsFile=/dev/null -oStrictHostKeyChecking=no -oPort=${PORT} ${ftp_username}@${ftp_ip} >/dev/null 2>&1;
	else
		${SSH_SFTP} -C -oBatchMode=no -b ${TMP_BATCH_FILE} -oUserKnownHostsFile=/dev/null -oStrictHostKeyChecking=no -oPort=${PORT} -oIdentityFile=${SSH_KEY} ${ftp_username}@${ftp_ip} >/dev/null 2>&1;
	fi;
	RET=$?;
	
	[ -f "${TMP_BATCH_FILE}" ] && rm -f ${TMP_BATCH_FILE};
	
	if [ "${RET}" -ne 0 ]; then
		echo "[upload] sftp return code: $RET";
	fi
}
# Poralix

#######################################################
# SETUP

if [ ! -e $TOUCH ] && [ -e /usr/bin/touch ]; then
	TOUCH=/usr/bin/touch
fi
if [ ! -x ${EXPR} ] && [ -x /bin/expr ]; then
	EXPR=/bin/expr
fi

if [ ! -e "${ftp_local_file}" ]; then
	echo "Cannot find backup file ${ftp_local_file} to upload";

	/bin/ls -la ${ftp_local_path}

	/bin/df -h

	exit 11;
fi

get_md5() {
	MF=$1

	if [ ${OS} = "FreeBSD" ]; then
		MD5SUM=/sbin/md5
	else
		MD5SUM=/usr/bin/md5sum
	fi
	if [ ! -x ${MD5SUM} ]; then
		return
	fi

	if [ ! -e ${MF} ]; then
		return
	fi

	if [ ${OS} = "FreeBSD" ]; then
		FMD5=`$MD5SUM -q $MF`
	else
		FMD5=`$MD5SUM $MF | cut -d\  -f1`
	fi

	echo "${FMD5}"
}

#######################################################

CFG=${ftp_local_file}.cfg
/bin/rm -f $CFG
$TOUCH $CFG
/bin/chmod 600 $CFG

RET=0;


#######################################################
TIMEOUT=120

#dynamic timeout for nctpput.
#Curl kicks the control connection with keep-alive pings by default.
SIZE_GIG=0
SECONDS_PER_GIG=120
if [ -x ${DU} ]; then
	if [ "${OS}" = "FreeBSD" ]; then
		SIZE_GIG=`BLOCKSIZE=G ${DU} -A ${ftp_local_file} | cut -f1`
	else
		SIZE_GIG=`${DU} --apparent-size --block-size=1G ${ftp_local_file} | cut -f1`
	fi

	if [ "${SIZE_GIG}" -gt 1 ]; then
		NEW_TIMEOUT=$TIMEOUT

		if [ -x ${BC} ]; then
			NEW_TIMEOUT=`echo "${SIZE_GIG} * ${SECONDS_PER_GIG}" | ${BC}`
		elif [ -x ${EXPR} ]; then
			NEW_TIMEOUT=`${EXPR} ${SIZE_GIG} \* ${SECONDS_PER_GIG}`
		else
			echo "Cannot find ${BC} nor ${EXPR} for ftp upload timeout change on large file: ${SIZE_GIG} Gig.";
		fi

		#make sure it's a useful number
		if [ "${NEW_TIMEOUT}" -gt "${TIMEOUT}" ]; then
			TIMEOUT=${NEW_TIMEOUT};
		fi
	fi
fi

#######################################################
# FTP
upload_file()
{
	if [ ! -e $FTPPUT ]; then
		echo "";
		echo "*** Backup not uploaded ***";
		echo "Please install $FTPPUT by running:";
		echo "";
		echo "cd /usr/local/directadmin/scripts";
		echo "./ncftp.sh";
		echo "";
		exit 10;
	fi

	/bin/echo "host $ftp_ip" >> $CFG
	/bin/echo "user $ftp_username" >> $CFG
	/bin/echo "pass $ftp_password" >> $CFG

	if [ ! -s ${CFG} ]; then
		echo "${CFG} is empty. ncftpput is not going to be happy about it.";
		ls -la ${CFG}
		ls -la ${ftp_local_file}
		df -h
	fi

	$FTPPUT -f $CFG -V -t ${TIMEOUT} -P $PORT -m "$ftp_path" "$ftp_local_file" 2>&1
	RET=$?

	if [ "${RET}" -ne 0 ]; then
		echo "ncftpput return code: $RET";
	fi
}

#######################################################
# FTPS
upload_file_ftps()
{
	if [ ! -e ${CURL} ]; then
		CURL=/usr/bin/curl
	fi

	if [ ! -e ${CURL} ]; then
		echo "";
		echo "*** Backup not uploaded ***";
		echo "Please install curl by running:";
		echo "";
		echo "cd /usr/local/directadmin/custombuild";
		echo "./build curl";
		echo "";
		exit 10;
	fi

	/bin/echo "user =  \"$ftp_username:$ftp_password\"" >> $CFG

	if [ ! -s ${CFG} ]; then
		echo "${CFG} is empty. curl is not going to be happy about it.";
		ls -la ${CFG}
		ls -la ${ftp_local_file}
		df -h
	fi

	#ensure ftp_path ends with /
	ENDS_WITH_SLASH=`echo "$ftp_path" | grep -c '/$'`
	if [ "${ENDS_WITH_SLASH}" -eq 0 ]; then
		ftp_path=${ftp_path}/
	fi

	${CURL} --config ${CFG} --ftp-ssl-reqd -k --silent --show-error --ftp-create-dirs --upload-file $ftp_local_file  ftp://$ftp_ip:${PORT}/$ftp_path$ftp_remote_file 2>&1
	RET=$?

	if [ "${RET}" -ne 0 ]; then
		echo "curl return code: $RET";
	fi
}

#######################################################
# Start

if [ "${USE_SSH}" = "1" ]; then
	upload_file_ssh;
else
	if [ "${FTPS}" = "1" ]; then
		upload_file_ftps
	else
		upload_file
	fi
fi;

if [ "${RET}" = "0" ] && [ "${MD5}" = "1" ]; then
	MD5_FILE=${ftp_local_file}.md5
	M=`get_md5 ${ftp_local_file}`
	if [ "${M}" != "" ]; then
		echo "${M}" > ${MD5_FILE}

		ftp_local_file=${MD5_FILE}
		ftp_remote_file=${ftp_remote_file}.md5

		if [ "${USE_SSH}" = "1" ]; then
			upload_file_ssh;
		else
			if [ "${FTPS}" = "1" ]; then
				upload_file_ftps
			else
				upload_file
			fi
		fi;
	fi
fi

/bin/rm -f $CFG

exit $RET

