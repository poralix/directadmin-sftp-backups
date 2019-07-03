<p align="center"><a href="https://directadmin.com"><img src="https://directadmin.com/img/logo/logo_directadmin.svg" alt="Directadmin" width="440px"/></a></p>

## Description

A set of patched scripts to allow Directadmin to 

- store
- list
- download

backups over SSH using SFTP.

## Author of patches

Alex Grebenschikov, Poralix, (www.poralix.com), 2018

## PLUGIN VERSION

- Version: Version: 0.1.poralix $ Tue Jan 30 12:43:50 +07 2018
- Last modified: Tue Jan 30 12:43:50 +07 2018
- Repository URL: https://github.com/poralix/directadmin-sftp-backups
- Report issues to URL: https://github.com/poralix/directadmin-sftp-backups/issues
- Home page: www.poralix.com


## Requirements

**For Debian servers**:

```
apt-get install sshpass
```

**For CentOS**:

```
yum install sshpass
```

## Installation

```
cd /usr/local/directadmin/scripts/custom/
git clone https://github.com/poralix/directadmin-sftp-backups.git
cp -f directadmin-sftp-backups/ftp_download.php ./
cp -f directadmin-sftp-backups/ftp_list.php ./
cp -f directadmin-sftp-backups/ftp_upload.php ./
chmod 700 ftp_*.php
chown diradmin:diradmin ftp_*.php
```

## Usage

Go to directadmin `Admin login -> Admin Backup/Transfer` and set:

- Username: **real ssh username**
- Password: **real ssh password or full to SSH RSA key**
- Remote Path: **full path to a backup directory from a remote server**
- Port: **22**

The script will detect the specified port **22** and will use SFTP to connect to SSH port. 
If you want FTP/FTPS just change port to 21 and update other credentials (the same script 
can be used for FTP/FTPS/SSH/SFTP).
