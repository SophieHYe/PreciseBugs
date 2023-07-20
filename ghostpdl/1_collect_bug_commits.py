#!/usr/bin/python
import sys, os, time, subprocess,fnmatch, shutil, csv,re, datetime
from time import strptime



def collectBugFixCommitID():
    logs = os.popen('git log --pretty=format:"%h\t%p\t%cd\t%s" -200000 ').read()
    commits = logs.split('\n')
    for commit in commits:
        print(commit)
        if 'fix' in commit or 'Fix' in commit or 'bug' in commit or 'Bug' in commit:
            with open('../1-ghostpdl-all-bug-commit.csv', 'a') as csvfile:
                csvfile.write(commit+'\n')



if __name__ == '__main__':
    os.chdir('ghostpdl')
    collectBugFixCommitID()