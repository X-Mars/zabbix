# LIBEVENT_CHECK_CONFIG ([DEFAULT-ACTION])
# ----------------------------------------------------------
#
# Checks for libevent.  DEFAULT-ACTION is the string yes or no to
# specify whether to default to --with-libevent or --without-libevent.
# If not supplied, DEFAULT-ACTION is no.
#
# This macro #defines HAVE_LIBEVENT if a required header files is
# found, and sets @LIBEVENT_LDFLAGS@ and @LIBEVENT_CFLAGS@ to the necessary
# values.
#
# This macro is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

AC_DEFUN([LIBEVENT_TRY_LINK],
[
AC_LINK_IFELSE([AC_LANG_PROGRAM([[
#include <stdlib.h>
#include <event.h>
#include <event2/thread.h>
]], [[
	evthread_use_pthreads();
	event_init();
]])],[found_libevent="yes"],[])
])dnl

AC_DEFUN([LIBEVENT_ACCEPT_VERSION],
[
	if test -f $1; then
		minimal_libevent_version=0x02000a00
		found_libevent_version=`cat $1 | $EGREP \#define.*'_NUMERIC_VERSION ' | $AWK '{print @S|@3;}'`

		# compare versions lexicographically
		libevent_version_check=`expr $found_libevent_version \>\= $minimal_libevent_version`

		if test "$libevent_version_check" = "1"; then
			accept_libevent_version="yes"
		else
			accept_libevent_version="no"
		fi;
	else
		accept_libevent_version="no"
	fi;
])dnl


AC_DEFUN([LIBEVENT_CHECK_CONFIG],
[
	AC_ARG_WITH([libevent],[
If you want to specify libevent installation directories:
AS_HELP_STRING([--with-libevent@<:@=DIR@:>@], [use libevent from given base install directory (DIR), default is to search through a number of common places for the libevent files.])],
		[
			if test "x$withval" = "xyes"; then
				if test -f /usr/local/include/event.h; then withval=/usr/local; else withval=/usr; fi
			fi

			LIBEVENT_CFLAGS="-I$withval/include"
			LIBEVENT_LDFLAGS="-L$withval/lib"
			_libevent_dir_set="yes"
		]
	)

	AC_ARG_WITH([libevent-include],
		AS_HELP_STRING([--with-libevent-include@<:@=DIR@:>@],
			[use libevent include headers from given path.]
		),
		[
			LIBEVENT_CFLAGS="-I$withval"
			_libevent_dir_set="yes"
		]
	)

	AC_ARG_WITH([libevent-lib],
		AS_HELP_STRING([--with-libevent-lib@<:@=DIR@:>@],
			[use libevent libraries from given path.]
		),
		[
			LIBEVENT_LDFLAGS="-L$withval"
			_libevent_dir_set="yes"
		]
	)

	AC_MSG_CHECKING(for libevent support)

	if test "x$ARCH" = "xopenbsd"; then
		LIBEVENT_LIBS="-levent_core -levent_pthreads"
	else
		LIBEVENT_LIBS="-levent -levent_pthreads"
	fi

	if test -n "$_libevent_dir_set" -o -f /usr/include/event.h; then
		found_libevent="yes"
		if test "x$withval" = "xyes"; then
			if test -f /usr/local/include/event.h; then withval=/usr/local; else withval=/usr; fi
		fi
                LIBEVENT_ACCEPT_VERSION([$withval/include/event2/event-config.h])
	elif test -f /usr/local/include/event.h; then
		LIBEVENT_CFLAGS="-I/usr/local/include"
		LIBEVENT_LDFLAGS="-L/usr/local/lib"
		found_libevent="yes"
                LIBEVENT_ACCEPT_VERSION([/usr/local/include/event2/event-config.h])
	else
		found_libevent="no"
		AC_MSG_RESULT(no)
	fi

	if test "x$found_libevent" = "xyes"; then
		am_save_CFLAGS="$CFLAGS"
		am_save_LDFLAGS="$LDFLAGS"
		am_save_LIBS="$LIBS"

		CFLAGS="$CFLAGS $LIBEVENT_CFLAGS"
		LDFLAGS="$LDFLAGS $LIBEVENT_LDFLAGS"
		LIBS="$LIBS $LIBEVENT_LIBS"

		found_libevent="no"
		LIBEVENT_TRY_LINK([no])

		CFLAGS="$am_save_CFLAGS"
		LDFLAGS="$am_save_LDFLAGS"
		LIBS="$am_save_LIBS"
	fi

	if test "x$found_libevent" = "xyes"; then
		AC_DEFINE([HAVE_LIBEVENT], 1, [Define to 1 if you have the 'libevent' library (-levent)])
		AC_MSG_RESULT(yes)
	else
		LIBEVENT_CFLAGS=""
		LIBEVENT_LDFLAGS=""
		LIBEVENT_LIBS=""
	fi

	AC_SUBST(LIBEVENT_CFLAGS)
	AC_SUBST(LIBEVENT_LDFLAGS)
	AC_SUBST(LIBEVENT_LIBS)
])dnl
