#!/usr/bin/python
import sys, os, time, subprocess,fnmatch, shutil, csv,re, datetime
from time import strptime
from subprocess import Popen, PIPE
import requests





def requestType(issueID,tokenID):
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
        "x-xsrf-token": tokenID
      }


    response = requests.post(url, headers = header , json=body)
    print(response)
    for line in str(response.text).split('\n'):
        if '\"summary\":' in line and ':' in line:
            crashType = line.split('\"summary\":')[1]
            crashType=crashType.replace('\"','')  
            crashType=crashType[1:len(crashType)-1]
    
    
    
    print('+++++++++++++++++++++++++++++++'+crashType+'+++++++++++++++++++++++++++')                   
    return crashType

def most_frequent(List):
    print(str(List))
    counter = 0
    num = List[0]
     
    for i in List:
        curr_frequency = List.count(i)
        if(curr_frequency> counter):
            counter = curr_frequency
            num = i
 
    return num




def analyzeBugs(projectName,projectGithubLink,gitName):
    currentpath=os.path.dirname(os.path.realpath(__file__))
    print(currentpath)
    extentions=[]
    os.chdir('..')
    os.system('rm -rf '+gitName)
    if os.path.exists('./bugs/'):
        print(os.path.exists('./bugs/'))

        bugs = os.listdir('./bugs/')       
        print(bugs)

        modifies = os.listdir('./bugs/'+bugs[0]+'/buggy')
        for m in modifies:
            extention = m.split('.')[1]
            print(extention)
            extentions.append(extention)
            
        most_extention = most_frequent(extentions)  
        print(most_extention)
        
        os.chdir('..')
        if not os.path.exists(most_extention):
            os.system('mkdir '+most_extention)
        os.system('mv '+projectName +'  ./'+most_extention+'/')
            
            



def includeBuggyCode(projectName,projectGithubLink,gitName):
    os.chdir('..')
    os.system('rm -rf '+projectName)
    os.system('git clone '+ projectGithubLink)
    os.chdir(gitName)
    with open('../'+projectName+'-oss-fuzz.csv', 'r') as bugs:
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




def processOSSFuzz(projectName,projectGithubLink,gitName,tokenID):
    currentpath=os.path.dirname(os.path.realpath(__file__))
    print('processOSSFuzz',currentpath)
    os.chdir('..')
    os.system('rm -rf '+gitName)
    os.system('git clone '+projectGithubLink)
    os.chdir(gitName)
    with open('../count.txt', 'r') as bugs:
        lines = bugs.readlines()
        for line in lines:
            lst = line.split('\t')
            if len(lst)<2:
                continue
            commit = lst[0]
            parentCommit= lst[1]
            dateInfo= lst[2]
            logs = lst[4]
            fuzzlink='https://bugs.chromium.org/p/oss-fuzz/issues/detail?id='
            fuzzID=''
            if fuzzlink in logs or 'oss-fuzz ' in logs or '/clusterfuzz-testcase-' in logs:
                if fuzzlink in logs:
                    fuzzID = logs.split(fuzzlink)[1]
                    fuzzID=fuzzID.split('.')[0]
                    fuzzID=fuzzID.split(')')[0]
                    fuzzID=fuzzID.split('(')[0]
                    fuzzID=fuzzID.split(' ')[0]
                    fuzzID=fuzzID.replace('\n','')
                
                elif 'oss-fuzz ' in logs:
                    possibleFuzzID = logs.split('oss-fuzz ')[1]
                    possibleFuzzID=possibleFuzzID.split(' ')[0]
                    for f in possibleFuzzID:
                        if f.isnumeric():
                            fuzzID=fuzzID+f   
                            
                elif '/clusterfuzz-testcase-'  in logs:
                    possibleFuzzID = logs.split('/clusterfuzz-testcase-')[0]
                    possibleFuzzID = possibleFuzzID.split(' ')[-1]
                    for f in possibleFuzzID:
                        if f.isnumeric():
                            fuzzID=fuzzID+f   
                    
                    
                
                print('=====fuzzID======:',fuzzID)
                ossFuzzLink=fuzzlink+fuzzID
                crashType=''
                crashType = requestType(fuzzID,tokenID)   
            
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
                diffName= projectName+'-'+year+str(month)+date+'-'+commit
                line=line.replace('\n','')
                with open('../'+projectName+'-oss-fuzz.csv', 'a') as ossbugs:
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

                    
                    
                    
                    

def collectOSSFUZZCommitID(projectName,projectGithubLink,gitName):

    os.system('git clone '+projectGithubLink)
    os.chdir(gitName)
    logs = os.popen('git log --pretty=format:"%h\t%p\t%cd\t%s" -200000 ').read()   
    commits = logs.split('\n')
    for commit in commits:
        print(commit)
        if 'fix' in commit or 'Fix' in commit or 'bug' in commit or 'Bug' in commit :
            with open('../'+projectName+'-commits.csv', 'a') as csvfile:
                csvfile.write(commit+'\n')
    
    
    with open('../'+projectName+'-commits.csv', 'r') as bugs:
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
            
            if 'oss-fuzz' in logs or (' oss ' in logs and ' fuzz ' in logs):
                with open('../count.txt', 'a') as saveDiff:
                    line=line.replace('\n','').replace('\r','')
                    logs=logs.replace('\n','').replace('\r','')
                    saveDiff.write(line+'\t'+logs+'\n')
    
    
    
    print(projectName,projectGithubLink,gitName)
    currentpath=os.path.dirname(os.path.realpath(__file__))
    print(currentpath)



if __name__ == '__main__':
    
    with open('oss-fuzz-statictics.csv','r') as fuzzProjects:
        projects = fuzzProjects.readlines()
        for project in projects[0:1]:
                        
            
            projectName=project.split('\t')[0]
            projectGithubLink=project.split('\t')[2]
  
            os.system('mkdir '+projectName)
            os.chdir(projectName)
        
            gitName=projectGithubLink.split('/')[-1]
            gitName=gitName.split('.')[0]
            collectOSSFUZZCommitID(projectName,projectGithubLink,gitName)
            processOSSFuzz(projectName,projectGithubLink,gitName,'bQNrzOLYPdon6CBDeRqViDoxNjkwMTYwNjc3')
            includeBuggyCode(projectName,projectGithubLink,gitName)
            analyzeBugs(projectName,projectGithubLink,gitName)
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    