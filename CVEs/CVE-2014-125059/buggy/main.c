/* sternenblog
 * A small file based blog software
 * intended to run as a CGI script
 * written in C by Lukas Epple aka
 * sternenseemann */
#define _POSIX_C_SOURCE 200809L
#define _XOPEN_SOURCE 501
#include <stdio.h>
#include <stdlib.h>
#include <dirent.h>
#include <sys/types.h>
#include <string.h>
#include <errno.h>
#include <time.h>
#include "template.h"
#include "config.h"

/* returns a blogpost struct
 * for a path */
struct blogpost make_blogpost(char path[]);

/* constructs a path from a dirent
 * and calls make_blogpost */
struct blogpost make_blogpost_from_dirent(struct dirent *file);

/* generates the template
 * for the index page */
void blog_index(void);

/* generates the template for 
 * a single view of a blog post*/
void blog_post(char post_path[]);

/* generates the rss feed */
void blog_rss(void);
/* checks if a file exists
 * returns 0 if not
 * returns 1 if
 * returns -1 if the file isn't accesible
 * for some other reason */
int file_exists(char path[]);

/* sends a CGI/HTTP header */
void send_header(char key[], char val[]);

/* terminates the header section of a
 * CGI/HTTP Response */
void terminate_headers(void);

/* function that filters out the .files
 * for scandir */
int no_dotfiles(const struct dirent *file);

int main(void) {
	char *path_info = getenv("PATH_INFO");
	
	if(path_info == NULL || path_info[0] == '\0' || strcmp(path_info, "/") == 0) {
		blog_index();
	} else if(strcmp(path_info, "/rss.xml") == 0) {
		blog_rss();
	} else {
		unsigned long bufsize = strlen(BLOG_DIR) + strlen(path_info);
		char post_path[bufsize];
		strcpy(post_path, BLOG_DIR);
		strcat(post_path, path_info);

		blog_post(post_path);
	}

	return EXIT_SUCCESS;
}

/**************************
 * functions for dealing
 * with struct blogpost's
 *************************/

struct blogpost make_blogpost(char path[]) {
	struct tm blog_tm;
	memset(&blog_tm, 0, sizeof blog_tm);

	struct blogpost struct_to_return;


	
	/* find the last '/' to
	 * get 2014-12-12-12-12-lala
	 * from /path/to/2014-12-12-12-12-lala */

	char *last_slash_position = strrchr(path, '/');
	if(last_slash_position == NULL) {
		fprintf(stderr, "There's something incredibly wrong with the path (%s) supplied to make_blogpost\n", path);
		exit(EXIT_FAILURE);
	}

	/* this parses the filename that is linke
	 * year-month-day-hour-minute-title */

	/* the string in the time part of the path */
	char time_string[4 + 1 + 2 + 1 + 2 + 1 + 2 + 1 + 2 + 1];
	strncpy(time_string, last_slash_position + 1, sizeof time_string - 1);
	time_string[sizeof time_string - 1] = '\0';

	strptime(time_string, "%Y-%m-%d-%H-%M", &blog_tm);

	struct_to_return.timestamp = mktime(&blog_tm);
	struct_to_return.path = malloc(strlen(path) * sizeof(char));
	strcpy(struct_to_return.path, path);

	/* let's build up the link */
	char *script_name = getenv("SCRIPT_NAME");
	if(script_name == NULL) {
		fprintf(stderr, "Died because of missing self-awareness\n");
		exit(EXIT_FAILURE);
	}
	int bufsize = strlen(script_name) +
		strlen(last_slash_position) + 1;
	struct_to_return.link = malloc(sizeof(char) * bufsize);
	strcpy(struct_to_return.link, script_name);
	strcat(struct_to_return.link, last_slash_position);

	/* that's all */

	return struct_to_return;
}

struct blogpost make_blogpost_from_dirent(struct dirent *post) {
	int bufsize = strlen(BLOG_DIR) + 1 + strlen(post->d_name) + 1;
	char buf[bufsize];

	strcpy(buf, BLOG_DIR);
	strcat(buf, "/");
	strcat(buf, post->d_name);

	return make_blogpost(buf);
}

void free_blogpost(struct blogpost to_free) {
	free(to_free.path);
	free(to_free.link);
	/* the rest lies in stack mem */
}

/************************
 * wrapper functions for
 * template generation
 ***********************/

void blog_index(void) {
	char *script_name = getenv("SCRIPT_NAME");
	struct dirent **dirlist;
	int dircount;

	if(script_name == NULL) {
		fprintf(stderr, "Died because of missing self-awareness\n");
		exit(EXIT_FAILURE);
	}

	send_header("Content-type", "text/html");
	terminate_headers();

	template_header();

	dircount = scandir(BLOG_DIR, &dirlist, no_dotfiles, alphasort);	

	if(dircount < 0) {
		fprintf(stderr, "An error occurred while scanning %s: %s\n", 
				BLOG_DIR, strerror(errno));
		exit(EXIT_FAILURE);
	}
	while(dircount--) {
		struct blogpost post = make_blogpost_from_dirent(dirlist[dircount]);

		/* finally if the file exists call the
		 * template function. Otherwise
		 * we do nothing. (this case is also
		 * FUCKING unlikely */
		if(file_exists(post.path) > 0) {
			template_post_index_entry(post);
		}
		
		free_blogpost(post);
		free(dirlist[dircount]);
	}
	free(dirlist);

	template_footer();
}

void blog_post(char post_path[]) {
	if(file_exists(post_path) > 0) {
		struct blogpost post = make_blogpost(post_path);

		send_header("Content-type", "text/html");
		terminate_headers();

		template_header();
		template_post_single_entry(post);
	} else {
		send_header("Content-type", "text/html");
		send_header("Status", "404 Not Found");
		terminate_headers();

		template_header();
		template_error_404();
	}

	template_footer();
}

void blog_rss(void) {
	struct dirent **dirlist;
	int dircount;
	
	/* construct the time,
	 * the blogpost was
	 * created */
	time_t timestamp;
	struct tm *timeinfo;
	char strtime_now[512];

	time(&timestamp);
	timeinfo = localtime(&timestamp);

	strftime(strtime_now, sizeof strtime_now, "%a, %d %b %G %T %z", timeinfo);

	
	char *script_name = getenv("SCRIPT_NAME");
	if(script_name == NULL) {
		fprintf(stderr, "Died because of missing self-awareness\n");
		exit(EXIT_FAILURE);
	}

	/* build the top part
	 * of the rss-feed */
	send_header("Content-type", "application/rss+xml");
	terminate_headers();

	printf("<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n"
	       "<rss version=\"2.0\">\n"
	       "<channel>\n"
	       "\t<title>%s</title>\n"
	       "\t<description>%s</description>\n"
	       "\t<link>" BLOG_SERVER_URL "%s</link>\n"
	       "\t<lastBuildDate>%s</lastBuildDate>\n"
	       "\t<pubDate>%s</pubDate>\n"
	       "\t<ttl>%d</ttl>\n",
	       BLOG_TITLE, BLOG_DESCRIPTION, 
	       script_name,
	       strtime_now, strtime_now,
	       BLOG_RSS_TTL);

	dircount = scandir(BLOG_DIR, &dirlist, no_dotfiles, alphasort);

	if(dircount < 0) {
		fprintf(stderr, "An error occurred while scanning %s: %s\n",
				BLOG_DIR, strerror(errno));
		exit(EXIT_FAILURE);
	}

	while(dircount--) {
		struct blogpost post;
		post = make_blogpost_from_dirent(dirlist[dircount]);
		char *last_slash_position = strrchr(post.path, '/');
		
		if(last_slash_position == NULL) {
			fprintf(stderr, "Malformed path %s in blog_rss\n", post.path);
			exit(EXIT_FAILURE);
		}

		FILE *fp = fopen(post.path, "r");
		char c;
		if(fp == NULL) {
			fprintf(stderr, "Could not open file: %s: %s\n",
					post.path, strerror(errno));
			exit(EXIT_FAILURE);
		}

		struct tm *timeinfo = localtime(&post.timestamp);
		char strtime_post[512];
		strftime(strtime_post, sizeof strtime_post, "%a, %d %b %G %T %z", timeinfo);
		
		printf("\t<item>\n"
		       "\t\t<title>%s</title>\n"
		       "\t\t<description><![CDATA[",
		       last_slash_position + 1);

		while((c = getc(fp)) != EOF) {
			printf("%c", c);
		}
		
		fclose(fp);

		printf("]]></description>\n"
		       "\t\t<link>" BLOG_SERVER_URL "%s</link>\n"
		       "\t\t<guid>" BLOG_SERVER_URL "%s</guid>\n"
		       "\t\t<pubDate>%s</pubDate>\n"
		       "\t</item>\n",
		       post.link, post.link, strtime_post);

		free_blogpost(post);
		free(dirlist[dircount]);
	}

	free(dirlist);

	printf("</channel>\n</rss>\n");
}

int file_exists(char path[]) {
	FILE *fp = fopen(path, "r");

	if(fp == NULL && errno == ENOENT) {
		return 0;
	} else if(fp == NULL) {
		// some other error occured
		return -1;
	} else {
		fclose(fp);
		return 1;
	}
}

/*********************
 * header utilities 
 ********************/
void send_header(char key[], char val[]) {
	printf("%s: %s\n", key, val);
}

void terminate_headers(void) {
	printf("\n");
}

/******************************
 * filter function
 * for scandir(3)
 *****************************/

int no_dotfiles(const struct dirent *file) {
	if(file->d_name[0] == '.') {
		return 0;
	} else {
		return 1;
	}
}
