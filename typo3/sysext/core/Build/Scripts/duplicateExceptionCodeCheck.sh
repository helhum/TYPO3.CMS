#!/bin/bash

#########################
#
# Find duplicate exception timestamps and list them
#
# Use within TYPO3 CMS source
#
#
# The script searchs for duplicate timestamps with
# two exceptions:
# 1. timestamps, defined by the "IGNORE" array
# 2. timestamps within an unit test
#
#
# @author  Christoph Kratz <ckr@rtp.ch>
# @author  Christian Kuhn <lolli@schwarzbu.ch>
# @date 2016-04-18
#
##########################

cd typo3/

# Array of timestamps which are allowed to be non-unique
IGNORE=("1270853884")

# The ack / ack-grep command can be different for different OS
ACK="ack-grep"

# Respect only php files and ignore files within a "Tests" directory
EXCEPTIONS=`$ACK --type php --ignore-dir Tests 'throw new' -A5 | grep '[[:digit:]]\{10\}'`

DUPLICATES=`echo $EXCEPTIONS | awk '{
    for(i=1; i<=NF; i++) {
        tmp=match($i, /[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]/);
        if(tmp) {
            print $i
        }
    }
}' | cut -d';' -f1 | tr -cd '0-9\012' | sort | uniq -d`

for CODE in $DUPLICATES; do

    # Ignore timestamps which are defined by the "IGNORE" array
    if [ ${IGNORE[@]} != $CODE ] ; then
        echo "Possible duplicate exception code $CODE": $ACK --type php $CODE
        exit 1
    fi

done

exit 0

