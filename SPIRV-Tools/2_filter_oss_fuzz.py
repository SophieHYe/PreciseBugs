#!/usr/bin/python
import sys, os, time, subprocess,fnmatch, shutil, csv,re, datetime
from time import strptime
import requests, os





if __name__ == '__main__':

    os.chdir('SPIRV-Tools')
    with open('../1-OpenSC-all-bug-commit.csv', 'r') as bugs:
        lines = bugs.readlines()
        for line in lines:
            lst = line.split('\t')
            if len(lst)<2:
                continue
            commit = lst[0]
            parentCommit= lst[1]
            dateInfo= lst[2]
            os.system('git reset --hard '+commit)
            logs = os.popen('git log -1').read()
            
            if 'oss-fuzz' in logs:
                with open('../count.txt', 'a') as saveDiff:
                    line=line.replace('\n','').replace('\r','')
                    logs=logs.replace('\n','').replace('\r','')
                    saveDiff.write(line+'\t'+logs+'\n')
                
