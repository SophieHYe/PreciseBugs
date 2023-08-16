/************************************************************************
* edit.c
* nodau console note taker
* Copyright (C) Lisa Milne 2010-2013 <lisa@ltmnet.com>
*
* edit.c is free software: you can redistribute it and/or modify it
* under the terms of the GNU General Public License as published by the
* Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* edit.c is distributed in the hope that it will be useful, but
* WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
* See the GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>
*
* In addition, as a special exception, the copyright holder (Lisa Milne)
* gives permission to link the code of this release of nodau with the
* OpenSSL project's "OpenSSL" library (or with modified versions of it
* that use the same license as the "OpenSSL" library), and distribute
* the linked executables. You must obey the GNU General Public License
* in all respects for all of the code used other than "OpenSSL". If you
* modify this file, you may extend this exception to your version of the
* file, but you are not obligated to do so. If you do not wish to do so,
* delete this exception statement from your version.
************************************************************************/

/* so that asprintf works */
#define _GNU_SOURCE
#include <unistd.h>
#include <ncurses.h>
#include <ctype.h>
#include <sys/stat.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <time.h>

#include "nodau.h"

/* local storage for note name and date */
static char* bname;
static char* bdate;

/* draw to the screen */
static void draw(char* data)
{
	/* clear the screen */
	clear();
	/* print the name and date in bold */
	attron(A_BOLD);
	printw("%s (%s):\n",bname,bdate);
	attroff(A_BOLD);
	/* print the note body */
	printw("%s",data);
	/* refresh the screen */
	refresh();
}

/* edit a note with the builtin editor */
static int edit_builtin(char* name, char* date, char* data)
{
	char buffer[256];
	int bl;
	/* still editing? */
	int quit = 0;
	/* character storage */
	int plch = 0;
	int lch = 0;
	int ch = 0;

	/* set the local data */
	bname = name;
	bdate = date;
	/* find the buffer length */
	bl = strlen(data);
	/* create the buffer */
	/* fill the buffer with 0's */
	memset(&buffer,0,256);

	/* if the note is too long, shorten it */
	if (bl > 255) {
		data[255] = 0;
		bl = 255;
	}

	/* put the note into the buffer */
	sprintf(buffer, "%s", data);

	/* init ncurses */
	initscr();
	/* no line buffering */
	cbreak();
	/* get all the keys */
	keypad(stdscr, TRUE);
	/* don't echo keypresses */
	noecho();

	/* while we are editing */
	while (!quit) {
		/* draw the screen */
		draw(buffer);
		/* set previous last char to last char */
		plch = lch;
		/* set last char to char */
		lch = ch;
		/* get char */
		ch = getch();
		/* if it's printable or newline */
		if (isprint(ch) || ch == '\n') {
			bl++;
			/* if the note is under 255 chars, add the char */
			if (bl < 255) {
				buffer[bl-1] = ch;
				buffer[bl] = 0;
			}
		/* backspace means delete a char */
		}else if (ch == 127 || ch == KEY_BACKSPACE) {
			/* if we've got one to delete */
			if (bl > 0) {
				bl--;
				buffer[bl] = 0;
			}
		}

		/* check for newline dot exit */
		if (plch == '\n' && lch == '.' && ch == '\n') {
			/* don't include the dot in the note */
			bl -= 3;
			buffer[bl] = 0;
			quit = 1;
		/* check for escape exit */
		}else if (ch == 27) {
			quit = 1;
		}
	}

	/* exit curses */
	endwin();

	/* save the note */
	if (!db_update(name,buffer))
		return 1;

	/* let the user know */
	printf("%s saved\n",name);

	return 0;
}

/* edit with an external editor */
static int edit_ext(char* editor, char* name, char* date, char* data)
{
	int fd;
	int st;
	int sz;
	char* b;
	char* l;
	char buff[512];
	pid_t pid;

	strcpy(buff,"/tmp/nodau.XXXXXX");
	fd = mkstemp(buff);

	if (fd < 0)
		return 1;

	pid = fork();

	if (pid < 0) {
		return 1;
	}else if (pid) {
		close(fd);
		waitpid(pid,&st,0);
		if (!st) {
			if ((fd = open(buff,O_RDONLY)) < 0)
				return 1;
			/* find the file length */
			sz = lseek(fd,0,SEEK_END);
			lseek(fd,0,SEEK_SET);
			if (sz) {
				/* load the note into memory */
				b = alloca(sz+1);
				if (sz != read(fd,b,sz))
					return 1;
				close(fd);
				/* delete the file */
				remove(buff);
				b[sz] = 0;
				/* find the note data */
				l = strstr(b,"-----");
				if (l) {
					/* save the note */
					l += 6;
					if (db_update(name,l))
						return 1;

					/* let the user know */
					printf("%s saved\n",name);
				}
			}
		}
		return st;
	}

	sz = strlen(name)+strlen(date)+strlen(data)+50;
	b = alloca(sz);

	/* insert data into file */
	sz = sprintf(
		b,
		"%s (%s)\nText above this line is ignored\n-----\n%s",
		name,
		date,
		data
	);
	if (write(fd,b,sz) != sz) {
		exit(1);
	}
	fsync(fd);
	close(fd);

	st = execl(editor,editor,buff,(char*)NULL);

	/* we should only ever get here if something goes wrong with exec */
	exit(st);

	/* and we shouldn't ever get here, but it stops the compiler complaining */
	return 1;
}

/* edit a note using data piped from stdin */
int edit_stdin(char* name, char* date, char* data, int append)
{
	char buff[1024];
	int l;
	int s;
	int r;
	char* d;
	char* b;

	/* get the initial buffer size */
	l = strlen(data);
	if (l < 512) {
		s = 512;
	}else{
		s = l*2;
	}

	d = malloc(s);
	if (!d)
		return 1;

	/* for append mode copy the old data to the start of the buffer */
	if (append && strcmp(data,"new entry")) {
		strcpy(d,data);
	}else{
		l = 0;
	}

	/* read it in */
	while ((r = read(STDIN_FILENO,buff,1024)) > 0) {
		/* extend the buffer as necessary */
		if (l+r+1 > s) {
			s = l+r+512;
			b = realloc(d,s);
			if (!b)
				return 1;
			d = b;
		}
		memcpy(d+l,buff,r);
		l += r;
	}

	/* make sure there's room for the nul byte */
	if (l+1 > s) {
		s = l+1;
		b = realloc(d,s);
		if (!b)
			return 1;
		d = b;
	}

	d[l] = 0;

	/* done */
	return db_update(name,d);
}

/* edit a note */
int edit(char* name, char* date, char* data)
{
	char* ed;
	char* pt;
	char* editor;
	char* p = NULL;
	struct stat st;

	if (!isatty(STDIN_FILENO))
		return edit_stdin(name,date,data,0);

	pt = getenv("PATH");

	ed = config_read("external_editor",NULL);
	if (!ed)
		ed = getenv("EDITOR");

	/* no editor or no path, use builtin */
	if (config_read("force_builtin_editor","true") || !ed || (ed[0] != '/' && !pt))
		return edit_builtin(name,date,data);

	/* find the executable */
	if (ed[0] == '/') {
		stat(ed,&st);
		/* check it exists */
		if (S_ISREG(st.st_mode)) {
			p = ed;
			editor = strdup(ed);
		}
	}else{
		p = strtok(pt,":");
		while (p) {
			p = strtok(NULL,":");

			if (asprintf(&editor,"%s/%s",p,ed) < 0)
				continue;

			stat(editor,&st);
			/* check it exists */
			if (S_ISREG(st.st_mode))
				break;

			free(editor);
		}
	}

	/* no executable, or fails to run, use builtin */
	if (!p || edit_ext(editor,name,date,data))
		return edit_builtin(name,date,data);

	return 0;
}
