#!/usr/bin/python
import sys, os, time, subprocess,fnmatch, shutil, csv,re, datetime
from time import strptime
import requests, os

def requestType(issueID):
    crashType=''
    url='https://bugs.chromium.org/prpc/monorail.Issues/GetIssue'
    
    
    body = {
        "issueRef":{  "localId":  issueID  ,  "projectName":"oss-fuzz"}

    }  
    print(body)

    header = {
        "accept": "application/json",
        "accept-language": "en-SE,en;q=0.9,zh-CN;q=0.8,zh;q=0.7,en-US;q=0.6,sq;q=0.5,sv;q=0.4",
        "content-type": "application/json",
        "sec-ch-ua": "\"Google Chrome\";v=\"113\", \"Chromium\";v=\"113\", \"Not-A.Brand\";v=\"24\"",
        "sec-ch-ua-mobile": "?0",
        "sec-ch-ua-platform": "\"macOS\"",
        "sec-fetch-dest": "empty",
        "sec-fetch-mode": "cors",
        "sec-fetch-site": "same-origin",
        "x-xsrf-token": "OUFqkhXDD2wlgPGim8UPsjoxNjg5ODIyMjIz"
      }


    response = requests.post(url, headers = header , json=body)
    print(response)
    for line in str(response.text).split('\n'):
        if '\"summary\":' in line and ':' in line:
            crashType = line.split('\"summary\":')[1]
            crashType=crashType.replace('\"','')  
            crashType=crashType[1:len(crashType)-1]
    
    
    
    print('+++++++++++++++++++++++++++++++'+crashType)                   
    return crashType


if __name__ == '__main__':
    os.system('rm -rf clamav')
    os.system('git clone https://github.com/Cisco-Talos/clamav.git')
    os.chdir('clamav')
    with open('../count.txt', 'r') as bugs:
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
            
            if 'https://bugs.chromium.org/p/oss-fuzz/issues/detail?id=' in logs:
                fuzzID = logs.split('https://bugs.chromium.org/p/oss-fuzz/issues/detail?id=')[1]
                fuzzID=fuzzID.split('.')[0]
                fuzzID=fuzzID.split(')')[0]
                fuzzID=fuzzID.split('(')[0]
                fuzzID=fuzzID.split(' ')[0]
                fuzzID=fuzzID.replace('\n','')
                
                ossFuzzLink='https://bugs.chromium.org/p/oss-fuzz/issues/detail?id='+fuzzID
                crashType = requestType(fuzzID)   
            
                #save diffs
                diffs = os.popen('git diff -u -1 '+parentCommit+' '+commit).read()
                modifications=''
                loc=''
                for d in diffs.split('\n'):
                    if '--- a/' in d :
                        modifications=modifications+d.split('--- a/')[1]+';'
                        modifications=modifications.replace('\n','')
                    if '@@ -' in d and ',' in d:
                        d=d.split(',')[0]
                        d=d.split('@@ -')[1]
                        loc=loc+d+';'                     
    
                modifications=modifications[0:-1]
                loc=loc[0:-1]
                
                commitDate= lst[2]
                dateInfo = commitDate.split(' ')
                month = dateInfo[1]
                month = strptime(month,'%b').tm_mon  
                if int(month)<10:
                    month='0'+str(month)                              
                date = dateInfo[2]
                if int(date)<10:
                    date='0'+str(date)                   
                year = dateInfo[4]
                diffName= 'Clamav-'+year+str(month)+date+'-'+commit
                line=line.replace('\n','')
                with open('../2-clamav-oss-fuzz.csv', 'a') as ossbugs:
                    ossbugs.write(diffName+'\t'+line+'\t'+fuzzID+'\t'+modifications+'\t'+loc+'\n')
                
                print(diffs)
                
                os.system('rm -rf  ../bugs/'+diffName)
                os.system('mkdir -p ../bugs/'+diffName+'/fixed')  
                os.system('mkdir -p ../bugs/'+diffName+'/buggy')
                                                                      
                #write diffs
                with open('../bugs/'+diffName+'/diff.txt', 'w') as saveDiff:
                    saveDiff.write(diffs)

                with open('../bugs/'+diffName+'/logs.txt', 'w') as saveDiff:
                    saveDiff.write(logs)                                            
                
                with open('../bugs/'+diffName+'/oss-fuzz.txt', 'w') as saveDiff:
                    saveDiff.write(ossFuzzLink)                                          
                
                with open('../bugs/'+diffName+'/type.txt', 'w') as saveDiff:
                    saveDiff.write(crashType)
                
                with open('../bugs/'+diffName+'/loc.txt', 'w') as saveDiff:
                    saveDiff.write(modifications+'\n'+loc) 
            
                for m in modifications.split(';'):
                    mfile=modifications.split('/')[-1]
                    mfile=mfile.split('.')[0]
                    print(mfile)
                    os.system('cp '+m +' ../bugs/'+diffName+'/fixed/')   
            
            
            
            
            
            
            