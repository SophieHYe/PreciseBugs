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
        "x-xsrf-token": "6U2zQlv35zJW_HfQMOKEGDoxNjg5Nzc2MTY5"
      }


    response = requests.post(url, headers = header , json=body)
    print(response)
    for line in str(response.text).split('\n'):
        if 'summary' in line and ':' in line:
            
            crashType = line.split(':')[-1]
            crashType=crashType[1:len(crashType)-2]
            
    
    
    
    print('+++++++++++++++++++++++++++++++'+crashType)                   
    return crashType


if __name__ == '__main__':
    os.system('rm -rf FFmpeg')
    os.system('git clone https://github.com/FFmpeg/FFmpeg.git')
    os.chdir('FFmpeg')
    with open('../1-ffmpeg-all-bug-commit.csv', 'r') as bugs:
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
            
#             if 'fuzzer-' in logs and 'Fixes: ' in logs and 'oss-fuzz' in logs:  
#                 print('no')
#             elif 'oss-fuzz' in logs:
#                 with open('../count.txt', 'a') as saveDiff:
#                     line=line.replace('\n','').replace('\r','')
#                     logs=logs.replace('\n','').replace('\r','')
#                     saveDiff.write(line+'\t'+logs+'\n')
                
                       
            if  'Fixes: ' in logs and 'oss-fuzz' in logs: 
                print(logs)
                
                fixes = logs.split('Fixes: ')
                print(fixes)
                
                if len(fixes)>2:
                    bug_Type = fixes[1]
                    fuzz = fixes[2]
                    
                elif len(fixes)==2:
                    bug_Type = lst[3]
                    fuzz = fixes[1]
                
                bug_Type=bug_Type.replace('\n','')

                print(bug_Type)
                ossFuzzID = fuzz.split('/')[0]
                ossFuzzID=ossFuzzID.replace(' ','')
                print(ossFuzzID)
                
    

                testID=''
                if '/clusterfuzz-testcase-minimized-' in logs:
                    ossFuzzID= logs.split('/clusterfuzz-testcase-minimized-')[0]
                    ossFuzzID=ossFuzzID.split(' ')[-1]
                    fuzz=logs.split('/clusterfuzz-testcase-minimized-')[1]
                    testID = fuzz.split(' ')[0]
                    print(testID)
                    testID = testID.split('\\n')[0]
                    testID=testID.split(' ')[0]
                    testID=testID.replace('\n','').replace('\r','')
                print(ossFuzzID)
                print(testID)
                ossFuzzLink='https://bugs.chromium.org/p/oss-fuzz/issues/detail?id='+ossFuzzID
                crashType = requestType(ossFuzzID)   
                
                testIDPath='https://oss-fuzz.com/download?testcase_id='+testID
                                                                                        
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
                diffName= 'FFmpeg-'+year+str(month)+date+'-'+commit
                line=line.replace('\n','')
                with open('../2-ffmpeg-oss-fuzz.csv', 'a') as ossbugs:
                    ossbugs.write(diffName+'\t'+line+'\t'+ossFuzzID+'\t'+testID+'\t'+modifications+'\t'+loc+'\n')
                
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
                    saveDiff.write(bug_Type+'\n')
                    saveDiff.write(crashType)                       
            
                with open('../bugs/'+diffName+'/test.txt', 'w') as saveDiff:
                    saveDiff.write(testIDPath)
                                        

                
                for m in modifications.split(';'):
                    mfile=modifications.split('/')[-1]
                    mfile=mfile.split('.')[0]
                    print(mfile)
                    os.system('cp '+m +' ../bugs/'+diffName+'/fixed/')    
    
    
            if 'fuzzer-' not in logs   and 'fixes: ' in logs and 'oss-fuzz' in logs: 
                
                
                fixes = logs.split('Fixes: ')
                print(fixes)
                
                if len(fixes)>2:
                    bug_Type = fixes[1]
                    fuzz = fixes[2]
                    
                elif len(fixes)==2:
                    bug_Type = lst[3]
                    fuzz = fixes[1]
                
                bug_Type=bug_Type.replace('\n','')

                print(bug_Type)
                ossFuzzID = fuzz.split('/')[0]
                ossFuzzID=ossFuzzID.replace(' ','')
                print(ossFuzzID)
                ossFuzzLink='https://bugs.chromium.org/p/oss-fuzz/issues/detail?id='+ossFuzzID

                crashType = requestType(ossFuzzID)   
    

                testID=''
                if '-' in fuzz:
                    testID = fuzz.split('-')[-1]
                    testID = testID.split('\\n')[0]
                    testID=testID.split(' ')[0]
                    testID=testID.replace('\n','').replace('\r','')
                print(testID)
                
                testIDPath='https://oss-fuzz.com/download?testcase_id='+testID
                                                                                        
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
                diffName= 'FFmpeg-'+year+str(month)+date+'-'+commit
                line=line.replace('\n','')
                with open('../2-ffmpeg-oss-fuzz.csv', 'a') as ossbugs:
                    ossbugs.write(diffName+'\t'+line+'\t'+ossFuzzID+'\t'+testID+'\t'+modifications+'\t'+loc+'\n')
                
                print(diffs)
                                             
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
                    saveDiff.write(bug_Type+'\n')
                    saveDiff.write(crashType)                       
            
                with open('../bugs/'+diffName+'/test.txt', 'w') as saveDiff:
                    saveDiff.write(testIDPath)
                                        

                
                for m in modifications.split(';'):
                    mfile=modifications.split('/')[-1]
                    mfile=mfile.split('.')[0]
                    print(mfile)
                    os.system('cp '+m +' ../bugs/'+diffName+'/fixed/')    
            
            
            
            
                     
#             if 'fuzzer-' in logs and 'Fixes: ' in logs and 'oss-fuzz' in logs: 
                
                
#                 fixes = logs.split('Fixes: ')
#                 print(fixes)
                
#                 if len(fixes)>2:
#                     bug_Type = fixes[1]
#                     fuzz = fixes[2]
                    
#                 elif len(fixes)==2:
#                     bug_Type = lst[3]
#                     fuzz = fixes[1]
                
#                 bug_Type=bug_Type.replace('\n','')

#                 print(bug_Type)
#                 ossFuzzID = fuzz.split('/')[0]
#                 ossFuzzID=ossFuzzID.replace(' ','')
#                 print(ossFuzzID)
#                 ossFuzzLink='https://bugs.chromium.org/p/oss-fuzz/issues/detail?id='+ossFuzzID

#                 crashType = requestType(ossFuzzID)

    
    

#                 testID=''
#                 if 'fuzzer-' in fuzz:
#                     testID = fuzz.split('fuzzer-')[1]
#                     testID = testID.split('\\n')[0]
#                     testID=testID.split(' ')[0]
#                     testID=testID.replace('\n','').replace('\r','')
#                 print(testID)
                
#                 testIDPath='https://oss-fuzz.com/download?testcase_id='+testID
                                                                                        
# #                 #save diffs
#                 diffs = os.popen('git diff -u -1 '+parentCommit+' '+commit).read()
#                 modifications=''
#                 loc=''
#                 for d in diffs.split('\n'):
#                     if '--- a/' in d :
#                         modifications=modifications+d.split('--- a/')[1]+';'
#                         modifications=modifications.replace('\n','')
#                     if '@@ -' in d and ',' in d:
#                         d=d.split(',')[0]
#                         d=d.split('@@ -')[1]
#                         loc=loc+d+';'
                    
    
    
#                 modifications=modifications[0:-1]
#                 loc=loc[0:-1]
                
#                 commitDate= lst[2]
#                 dateInfo = commitDate.split(' ')
#                 month = dateInfo[1]
#                 month = strptime(month,'%b').tm_mon  
#                 if int(month)<10:
#                     month='0'+str(month)                              
#                 date = dateInfo[2]
#                 if int(date)<10:
#                     date='0'+str(date)                   
#                 year = dateInfo[4]
#                 diffName= 'FFmpeg-'+year+str(month)+date+'-'+commit
#                 line=line.replace('\n','')
#                 with open('../2-ffmpeg-oss-fuzz.csv', 'a') as ossbugs:
#                     ossbugs.write(diffName+'\t'+line+'\t'+ossFuzzID+'\t'+testID+'\t'+modifications+'\t'+loc+'\n')
                
#                 print(diffs)
                              
                
#                 os.system('mkdir -p ../bugs/'+diffName+'/fixed')  
#                 os.system('mkdir -p ../bugs/'+diffName+'/buggy')
               
                    
                                    
#                 #write diffs
#                 with open('../bugs/'+diffName+'/diff.txt', 'w') as saveDiff:
#                     saveDiff.write(diffs)

#                 with open('../bugs/'+diffName+'/logs.txt', 'w') as saveDiff:
#                     saveDiff.write(logs)
                             
                
                
#                 with open('../bugs/'+diffName+'/oss-fuzz.txt', 'w') as saveDiff:
#                     saveDiff.write(ossFuzzLink)
                
                             
                
                
#                 with open('../bugs/'+diffName+'/type.txt', 'w') as saveDiff:
#                     saveDiff.write(bug_Type+'\n')
#                     saveDiff.write(crashType)
               
            
            
            
#                 with open('../bugs/'+diffName+'/test.txt', 'w') as saveDiff:
#                     saveDiff.write(testIDPath)
                    
# #                 with open('../bugs/'+diffName+'/Buggy.txt', 'w') as saveDiff:
                    
                              
                
                
                

                
#                 for m in modifications.split(';'):
#                     mfile=modifications.split('/')[-1]
#                     mfile=mfile.split('.')[0]
#                     print(mfile)
#                     os.system('cp '+m +' ../bugs/'+diffName+'/fixed/')
                    

                    
       
                
            
                    
            
                
                
                
                
                
                