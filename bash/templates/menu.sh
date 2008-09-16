#!/bin/bash
#/**
# * Template for interactive menu's
# * Will include all nescesary code to quickly deploy menu's.
# * 
# * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
# * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
# * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
# * @version   SVN: Release: $Id$
# * @link      http://kevin.vanzonneveld.net/
# *
# */

# Includes
###############################################################

# log() was auto-included from '/../functions/log.sh' by make.sh
#/**
# * Logs a message
# * 
# * @param string $1 String
# * @param string $2 Log level. EMERG exists app.
# */
function log(){
    # Levels:
    # EMERG
    # ALERT
    # CRIT
    # ERR
    # WARNING
    # NOTICE
    # INFO
    # DEBUG
    
    # Init
    local line="${1}"
    local levl="${2}"

    # Defaults
    [ -n "${levl}" ] || levl="INFO"
    local show=0
    
    # Allowed to show?  
    if [ "${levl}" == "DEBUG" ]; then
        if [ "${OUTPUT_DEBUG}" = 1 ]; then
            show=1
        fi
    else
        show=1
    fi
    
    # Show
    if [ "${show}" = 1 ];then
        echo "${levl}: ${1}"
    fi
    
    # Die?
    if [ "${levl}" = "EMERG" ]; then
        exit 1
    fi
}

# toUpper() was auto-included from '/../functions/toUpper.sh' by make.sh
#/**
# * Converts a string to uppercase
# * 
# * @param string $1 String
# */
function toUpper(){
   echo "$(echo ${1} |tr '[:lower:]' '[:upper:]')"
}

# commandInstall() was auto-included from '/../functions/commandInstall.sh' by make.sh
#/**
# * Tries to install a package
# * Also saved command location in CMD_XXX
# *
# * @param string $1 Command name
# * @param string $2 Package name
# */
function commandInstall() {
    # Init
    local command=${1}
    local package=${2}
    
    # Show
    echo "Trying to install ${package}"
    
    if [ -n "${CMD_APTITUDE}" ] && [ -x "${CMD_APTITUDE}" ]; then
        ${CMD_APTITUDE} -y install ${package}
    else
        echo "No supported package management tool found"
    fi
}

# commandTest() was auto-included from '/../functions/commandTest.sh' by make.sh
#/**
# * Tests if a command exists, and returns it's location or an error string.
# * Also saved command location in CMD_XXX.
# *
# * @param string $1 Command name
# * @param string $2 Package name
# */
function commandTest(){
    # Init
    local command=${1}
    local package=${2}
    local located=$(which ${command})
    
    # Checks
    if [ ! -n "${located}" ]; then
        echo "Command ${command} not found at all, please install before running this program."
    elif [ ! -x "${located}" ]; then
        echo "Command ${command} not executable at ${located}, please install before running this program."
    else
        echo "${located}" 
    fi
}

# commandTestHandle() was auto-included from '/../functions/commandTestHandle.sh' by make.sh
#/**
# * Tests if a command exists, tries to install package,
# * resorts to 'handler' argument on fail. 
# *
# * @param string $1 Command name
# * @param string $2 Package name. Optional. Defaults to Command name
# * @param string $3 Handler. Optional. (Any of the loglevels. Defaults to emerg to exit app)
# * @param string $4 Additional option. Optional.
# */
function commandTestHandle(){
    # Init
    local command="${1}"
    local package="${2}"
    local handler="${3}"
    local optionl="${4}"
    local success="0"
    local varname="CMD_$(toUpper ${command})"
    
    # Checks
    [ -n "${command}" ] || log "testcommand_handle needs a command" "EMERG"
    
    # Defaults
    [ -n "${package}" ] || package=${command}
    [ -n "${handler}" ] || handler="EMERG"
    [ -n "${optionl}" ] || optionl=""
    
    # Test command
    local located="$(commandTest ${command} ${package})"
    if [ ! -x "${located}" ]; then
        if [ "${optionl}" != "NOINSTALL" ]; then
            # Try automatic install
            commandInstall ${command} ${package}
             
            # Re-Test command
            located="$(commandTest ${command} ${package})"
            if [ ! -x "${located}" ]; then
                # Still not found
                log "${located}" "${handler}"
            else
                success=1
            fi
        else
            # Not found, but not going to install
            log "${located}" "${handler}"            
        fi
    else
        success=1
    fi
    
    if [ "${success}" == 1 ]; then
        log "Testing for ${command} succeeded" "DEBUG"
        # Okay, Save location in CMD_XXX variable 
        eval ${varname}="${located}"
    fi
}

# getWorkingDir() was auto-included from '/../functions/getWorkingDir.sh' by make.sh
#/**
# * Determines script's working directory
# * 
# * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
# * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
# * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
# * @version   SVN: Release: $Id: getWorkingDir.sh 89 2008-09-05 20:52:48Z kevin $
# * @link      http://kevin.vanzonneveld.net/
# * 
# * @param string PATH Optional path to add
# */
function getWorkingDir {
    echo $(realpath "$(dirname ${0})${1}")
}

# getTempFile() was auto-included from '/../functions/getTempFile.sh' by make.sh
#/**
# * Returns a unique temporary filename
# * 
# */
function getTempFile(){
	if [ -z "${CMD_TEMPFILE}" ]; then
	    echo "Dialog command not found or not initialized"
	    exit 1
	fi
	
	tempFile=`${CMD_TEMPFILE} 2>/dev/null` || tempFile=/tmp/test$$
	echo "" > ${tempFile};
	#trap "rm -f $tempFile" 0 1 2 5 15
	echo $tempFile
}

# kvzProgInstall() was auto-included from '/../functions/kvzProgInstall.sh' by make.sh
#/**
# * Tries to install a bash program from KvzLib
# * to /root/bin/
# *
# * @param string $1 KvzLib Program name
# */
function kvzProgInstall() {
    # Check if dependencies are initialized
    if [ -z "${CMD_WGET}" ]; then
        echo "wget command not found or not initialized" >&2
        exit 1
    fi

    if [ -z "${CMD_PWD}" ]; then
        echo "pwd command not found or not initialized" >&2
        exit 1
    fi

	
    # Init
    local PROGRAM=${1}
    local KVZLIBURL="http://kvzlib.net/b"
    local INSTALLDIR="/root/bin"
    local OLDDIR=$(${CMD_PWD})
    local URL=${KVZLIBURL}/${PROGRAM}
    
    [ -d "${INSTALLDIR}" ] || mkdir -p ${INSTALLDIR}
    cd ${INSTALLDIR}
    
    # Show
    ${CMD_WGET} -q ${URL}
    cd ${OLDDIR}  
    
    if [ $? != 0 ];
        echo "download of ${URL} failed" >&2
        exit 1
    fi
}

# boxList() was auto-included from '/../functions/boxList.sh' by make.sh
#/**
# * Displays a List dialog
# * 
# * @param string $1 Title
# * @param string $2 Description
# * @param string $3 Items, delimited with = and |
# */
function boxList(){
	# Check if dependencies are initialized
    if [ -z "${CMD_DIALOG}" ]; then
        echo "dialog command not found or not initialized" >&2
        exit 1
    fi

    if [ -z "${CMD_SED}" ]; then
        echo "sed command not found or not initialized" >&2
        exit 1
    fi
    
    if [ -z "${CMD_TEE}" ]; then
        echo "tee command not found or not initialized" >&2
        exit 1
    fi
    
    if [ -z "${CMD_AWK}" ]; then
        echo "awk command not found or not initialized" >&2
        exit 1
    fi
    
	# Determine static arguments
	local TITLE="${1}"
	local DESCR="${2}"
	local ITEMS=$(echo "${3}" |${CMD_SED} 's# #_#g')
	
	local ITEMSNEW=""
	local i=0
	local combi=""
	local answerFile=""
	local answer=""
	local retVal=""

    # Open tempfile for non-blocking storage of choices
    answerFile=$(getTempFile)
    
    # Collect remaining arguments items
    for couple in $(echo "${ITEMS}" |${CMD_SED} 's#|# #g'); do
        key=$(echo "${couple}" |${CMD_AWK} -F '=' '{print $1}')
        val=$(echo "${couple}" |${CMD_AWK} -F '=' '{print $2}' |${CMD_SED} 's#_# #g')
        
        ITEMSNEW="${ITEMSNEW}\"${key}\" \"${val}\" "
    done
    
    # Open dialog
    eval ${CMD_DIALOG} --clear --title "${TITLE}" --menu "${DESCR}" 16 51 6 ${ITEMSNEW} 2> ${answerFile}
    retVal=$?
    
    # OK?
    answer=$(cat ${answerFile})
    rm -f ${answerFile}
    
    case ${retVal} in
        0)
            # Save in global variable for non-blocking storage
            boxReturn=${answer}
        ;;
        1)
            #clear
            echo "Cancel ${retVal} pressed. Result:" >&2
            cat ${answerFile} >&2
            exit 1
        ;;
        255)
            #clear
            echo "ESC ${retVal} pressed. Result:" >&2
            cat ${answerFile} >&2
            exit 1
        ;;
    esac
}

# boxYesNo() was auto-included from '/../functions/boxYesNo.sh' by make.sh
#/**
# * Displays a Yes/No dialog
# * Returns 1 on yes, 0 on no
# * 
# * @param string $1 Title
# * @param string $2 Description
# * @param string $3 Options
# */
function boxYesNo(){
    # Check if dependencies are initialized
    if [ -z "${CMD_DIALOG}" ]; then
        echo "Dialog command not found or not initialized" >&2
        exit 1
    fi

    # Determine static arguments
    local TITLE="${1}"
    local DESCR="${2}"
    local OPTIONS="${3}"
    
    local retVal=""
    
    # Open dialog    
    ${CMD_DIALOG} ${OPTIONS} --title "${1}" --clear --yesno "${2}" 10 70
    retVal=$?
    
    if [ "${retVal}" = 1 ]; then
        boxReturn=0
    elif [ "${retVal}" = 0 ]; then
        boxReturn=1
    else
        #clear
        echo "ESC ${retVal} pressed. Result:" >&2
        exit 1
    fi
}


# Check for program requirements
###############################################################
commandTestHandle "bash" "bash" "EMERG" "NOINSTALL"
commandTestHandle "aptitude" "aptitude" "DEBUG" "NOINSTALL" # Just try to set CMD_APTITUDE, produces DEBUG msg if not found
commandTestHandle "egrep" "pcregrep"
commandTestHandle "pwd"
commandTestHandle "awk"
commandTestHandle "sort"
commandTestHandle "uniq"
commandTestHandle "realpath"
commandTestHandle "sed"
commandTestHandle "tee"

commandTestHandle "tempfile"
commandTestHandle "dialog"

# Usage:
# boxList "Title" "Description" "option1=One, a good choice|option2=Two, maybe even better"
# echo ${boxReturn}
# 
# boxYesNo "Title" "Do you want to say no?" "0"
# echo ${boxReturn}

#set -x 
boxList "Title" "Description" "instkey=Installs SSH Keys remotely|setaptsources=Resets Ubuntu APT sources lists|showlogs=Shows all important logs|sysclone=a"