#!/usr/bin/env bash
###
# ABOUT  : telegraf monitoring script for gstat (geom stat) disk statistics
# AUTHOR : Matthias Breddin <mb@lunetics.com> (c) 2015
# LICENSE: GNU GPL v3
#
# This script parses the "gstat" batch output for available data
# Generates output suitable for Exec plugin of telegraf.
#
# It includes a few disks e.g. mfid, da, ad and zfs zvol's (without snapshots)
# Also cd, ufsid and gpt geom providers are ignored
#
# Requirements:
#       Freebsd
#
# Typical usage:
#   /usr/local/telegraf-plugins/freebsd-gstat/gstat.sh
`which sudo` `which gstat` -o -d -b -I 5s \
|pcregrep '(mfid|ad|da)[0-9]+|zvol((?!@|ufsid\/|cd\d+|gpt\/).)*$' \
|awk -v hostname=$(hostname -f) '{
    printf "gstat,host="hostname",disk="$15" transaction_queue_length="$1"i,ops_per_second="$2"i,percent_busy="$14","
    printf "reads_per_second="$3"i,read_kbps="$4"i,read_transcation_time_in_milliseconds="$5","
    printf "writes_per_second="$6"i,write_kbps="$7",write_transaction_time_in_milliseconds="$8","
    printf "deletes_per_second="$9"i,delete_kbps="$10"i,delete_transaction_time_in_milliseconds="$11","
    print  "other_per_second="$12",other_transaction_time_in_milliseconds="$13
}'
