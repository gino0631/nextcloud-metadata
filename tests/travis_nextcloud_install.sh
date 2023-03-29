#!/bin/bash

# Try to use automatic error detection
set -e

# Get script arguments
WORKDIR=$PWD
APP_NAME=$1
CORE_BRANCH=$2
DB=$3
echo "Work directory: $WORKDIR"
echo "Database: $DB"

# Clone Nextcloud and copy the app
cd ..
git clone --depth 1 -b $CORE_BRANCH https://github.com/nextcloud/server
cd server
git submodule update --init
cp -R $WORKDIR apps/$APP_NAME

# Create DB user and configure automatic setup parameters
DATADIR=$PWD/data-autotest

case $DB in
  pgsql)
    psql -c "CREATE USER oc_autotest WITH LOGIN SUPERUSER PASSWORD 'oc_autotest'"
    cat > config/autoconfig.php <<DELIM
<?php
\$AUTOCONFIG = array (
  'dbtype' => 'pgsql',
  'dbname' => 'oc_autotest',
  'dbuser' => 'oc_autotest',
  'dbpass' => 'oc_autotest',
  'dbhost' => 'localhost',
  'dbtableprefix' => 'oc_',
  'adminlogin' => 'admin',
  'adminpass' => 'admin',
  'directory' => '$DATADIR',
);
DELIM
    ;;

  *)
    echo "Unsupported database $DB" >&2
    exit 1
    ;;
esac

# Trigger Nextcloud installation
echo "Executing index.php"
php -f index.php
echo "DONE"

# Show status and enable the app
./occ check
./occ status
./occ app:enable $APP_NAME
./occ app:list
