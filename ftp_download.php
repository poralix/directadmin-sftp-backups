#!/bin/sh
# ===========================================================
# Original script was written by Directadmin
# ===========================================================
# Patched:
#  sFTP/SSH support added
#  By Alex Grebenschikov, Poralix, www.poralix.com
#  Last modified: Wed Dec 11 20:30:29 +07 2019
#  Version: 1.2.poralix.2 $ Wed Dec 11 20:30:29 +07 2019
#           1.2.poralix   $ Thu Sep 12 23:55:18 +07 2019
# ===========================================================

TMPDIR="/home/tmp";
PORT="${ftp_port}";
REMOTE_FILE="${ftp_remote_file}";
LOCAL_FILE="${ftp_local_file}";
LOCAL_PATH=`dirname ${LOCAL_FILE}`;

RANDNUM=`/usr/local/bin/php -r 'echo rand(0,10000);'`;
#we need some level of uniqueness, this is an unlikely fallback.
if [ "${RANDNUM}" = "" ]; then
	RANDNUM="${ftp_ip}";
fi

CFG="${TMPDIR}/${RANDNUM}.cfg";
rm -f "${CFG}";
touch "${CFG}";
chmod 600 "${CFG}";

DUMP="${TMPDIR}/${RANDNUM}.dump";
rm -f "${DUMP}";
touch "${DUMP}";
chmod 600 "${DUMP}";

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

download_file_ssh()
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
	echo "lcd ${LOCAL_PATH}" >> ${TMP_BATCH_FILE};
	echo "get ${REMOTE_FILE}" >> ${TMP_BATCH_FILE};
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
		echo "[download] sftp return code: $RET";
		cat ${DUMP};
	fi
}
# Poralix

#######################################################
# Start

if [ "${USE_SSH}" = "1" ]; then
	download_file_ssh;
else
	/usr/local/directadmin/scripts/ftp_download.php;
fi;

rm -f ${CFG};
rm -f ${DUMP};

exit ${RET}
