###
# ABOUT  : telegraf monitoring script for cpu frequency and temperature per core statistics
# AUTHOR : Matthias Breddin <mb@lunetics.com> (c) 2015
# LICENSE: GNU GPL v3
#
# This script parses sysctl variables
#
#
# Generates output suitable for Exec plugin of cpu.
# Integrates with the default cpuX schema from telegraf
#
# Requirements:
#       coretemp module:
#       load runtime: $ kldload coretemp
#       load at boot: add `coretemp_load="YES"` to /boot/loader.conf
#
# Typical usage:
#   /usr/local/telegraf-plugins/freebsd-cpu/cpu.sh
#
# Typical output:
#   cpu,host=foo.bar.com,cpu=cpu0 temperature=34,frequency=2668i
#   cpu,host=foo.bar.com,cpu=cpu1 temperature=35,frequency=2668i
#   cpu,host=foo.bar.com,cpu=cpu2 temperature=37,frequency=2668i
#   cpu,host=foo.bar.com,cpu=cpu3 temperature=37,frequency=2668i
#   cpu,host=foo.bar.com,cpu=cpu4 temperature=41,frequency=2668i
#   cpu,host=foo.bar.com,cpu=cpu5 temperature=41,frequency=2668i
#   cpu,host=foo.bar.com,cpu=cpu6 temperature=36,frequency=2668i
#   cpu,host=foo.bar.com,cpu=cpu7 temperature=36,frequency=2668i
#   ...
#
###
NUM_CPUS=`/sbin/sysctl -n hw.ncpu`;
for i in `seq 0 $(($NUM_CPUS -1))`
    do
    `which sysctl` -n dev.cpu.$i.temperature \
    | awk -v cpunum=$i -v hostname=$(hostname -f) -v freq=$(`which sysctl` -n dev.cpu.0.freq) ' BEGIN { ORS="";}{
                cputemp=sprintf("%.0f",$0);
                print "cpu,host="hostname",cpu=cpu"cpunum" temperature="cputemp",frequency="freq"i\n";
            }';
    done
