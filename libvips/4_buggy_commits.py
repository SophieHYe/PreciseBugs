#!/usr/bin/python
import sys, os, time, subprocess,fnmatch, shutil, csv,re, datetime
from time import strptime





if __name__ == '__main__':
    os.system('rm -rf libvips')
    os.system('git clone https://github.com/libvips/libvips.git')
    os.chdir('libvips')
    with open('../2-libvips-oss-fuzz.csv', 'r') as bugs:
        lines = bugs.readlines()
        for line in lines:
            lst = line.split('\t')
            if len(lst)<9:
                continue
            diffName = lst[0]
            buggyCommit= lst[2]
            modifications= lst[7]
            print(modifications)
            os.system('git reset --hard '+buggyCommit)
            for m in modifications.split(';'):
                mfile=modifications.split('/')[-1]
                mfile=mfile.split('.')[0]
                print(mfile)
                os.system('cp '+m +' ../bugs/'+diffName+'/buggy/') 