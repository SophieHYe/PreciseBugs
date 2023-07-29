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




def analyzeBugs(projectName,projectGithubLink,gitName,language):
    currentpath=os.path.dirname(os.path.realpath(__file__))
    print('analyzeBugs',currentpath)
   
    if not os.path.exists('../count.txt'):
        os.chdir('../../')
        os.system('rm -rf '+projectName)
        return

    extentions=[]
    os.chdir('..')
    os.system('rm -rf '+gitName)
    
    if os.path.exists('./bugs/'):
        if language  in '':
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
            language = most_extention
        
        
        
        os.chdir('..')
        if not os.path.exists(language):
            os.system('mkdir '+language)
            
        os.system('mv '+projectName +'  ./'+language+'/')
            
            



def includeBuggyFixCode(projectName,projectGithubLink,gitName,codeType):
    if not os.path.exists('../'+projectName+'-oss-fuzz.csv'):
        return
        
#     os.chdir('..')
#     os.system('rm -rf '+projectName)
#     os.system('git clone '+ projectGithubLink)
#     os.chdir(gitName)
    with open('../'+projectName+'-oss-fuzz.csv', 'r') as bugs:
        lines = bugs.readlines()
        for line in lines:
            lst = line.split('\t')
            if len(lst)<9:
                continue
            diffName = lst[0]
            fixCommit= lst[1]
            buggyCommit= lst[2]
            modifications= lst[7]
            print(modifications)
            
            if codeType in 'buggy':
                commitCode=buggyCommit
            elif codeType in 'fixed':
                commitCode=fixCommit
            
            os.system('git reset --hard '+commitCode)
            for m in modifications.split(';'):
                mfile=modifications.split('/')[-1]
                mfile=mfile.split('.')[0]
                print(mfile)
                os.system('cp '+m +' ../bugs/'+diffName+'/'+codeType+'/') 




def processOSSFuzz(projectName,projectGithubLink,gitName,tokenID):
               
    currentpath=os.path.dirname(os.path.realpath(__file__))
    print('processOSSFuzz',currentpath)
    
#     os.chdir(gitName)

    if not os.path.exists('../count.txt'):
        return

#     os.chdir('..')
#     os.system('rm -rf '+gitName)
#     os.system('git clone '+projectGithubLink)
#     os.chdir(gitName)
    
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
            logs = logs.replace(' issue ',' ').replace('.',' ').replace('  ',' ')
            os.system('git reset --hard '+commit)            
            
            fuzzlink='https://bugs.chromium.org/p/oss-fuzz/issues/detail?id='
            fuzzID=''
            if fuzzlink in logs or 'oss-fuzz ' in logs or '/clusterfuzz-testcase-' in logs or 'oss-fuzz#' in logs or 'oss-fuzz' in logs or 'oss-fuzz:' in logs :
                print(logs)

                if fuzzlink in logs:
                    print('!'*100 )
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
                            
                elif 'oss-fuzz#' in logs:
                    possibleFuzzID = logs.split('oss-fuzz#')[1]
                    possibleFuzzID=possibleFuzzID.split(' ')[0]
                    for f in possibleFuzzID:
                        if f.isnumeric():
                            fuzzID=fuzzID+f 
                
                elif 'oss-fuzz:' in logs:
                    possibleFuzzID = logs.split('oss-fuzz:')[1]
                    possibleFuzzID=possibleFuzzID.split(' ')[0]
                    for f in possibleFuzzID:
                        if f.isnumeric():
                            fuzzID=fuzzID+f
                            
                elif 'oss-fuzz' in logs:
                    possibleFuzzID = logs.split('oss-fuzz')[1]
                    possibleFuzzID=possibleFuzzID.split(' ')[0]
                    for f in possibleFuzzID:
                        if f.isnumeric():
                            fuzzID=fuzzID+f   
                            
              
                    
                    
                fuzzID=fuzzID.replace(' ','')    
                if fuzzID in '':
                    continue
                    
                    
                print('=====fuzzID======:',fuzzID)
                ossFuzzLink=fuzzlink+fuzzID
                crashType=''
                crashType = requestType(fuzzID,tokenID)   
                
                
                crashType=crashType.replace('  ',' ').replace('\n','')               
                crashType=crashType.strip()
                if crashType in '':
                    continue
            
                #save diffs  
                diffs=''
                try:
                    diffs = os.popen('git diff -u -1 '+parentCommit+' '+commit).read()
                except:
                    print('error in diffs')
                    
                if diffs in '':
                    continue
                
                modifications=''
                loc=''
                for d in diffs.split('\n'):
                    if '--- a/' in d  and '/dev/null' not in d and '/test/' not in d :
                        modifications=modifications+d.split('--- a/')[1]+';'
                        modifications=modifications.replace('\n','')
                    if '@@ -' in d and ',' in d:
                        d=d.split(',')[0]
                        d=d.split('@@ -')[1]
                        loc=loc+d+';'                     
    
    
                if modifications in '':
                    continue
                
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

    
    history = subprocess.Popen('git log --pretty=format:"%h\t%p\t%cd\t%s" -400000', shell=True, stdout=subprocess.PIPE)
    logs, stderr = history.communicate()    
    logs=str(logs)
    
    commits = logs.split('\\n')
    for commit in commits:
        if 'fix' in commit or 'Fix' in commit or 'bug' in commit or 'Bug' in commit :
            with open('../'+projectName+'-commits.csv', 'a') as csvfile:
                commit=commit.replace('\\t','\t')
                csvfile.write(commit+'\n')
    
    
    with open('../'+projectName+'-commits.csv', 'r') as bugs:
        lines = bugs.readlines()
        for line in lines:
            lst = line.split('\t')
            if len(lst)<3:
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
    
    with open('projects.csv','r') as fuzzProjects:
        projects = fuzzProjects.readlines()
        for project in projects:                       
            os.chdir('/home/heye/fuzzbugs/oss-fuzz')
            
            projectName=project.split('\t')[0]
            projectGithubLink=project.split('\t')[2]
            language=project.split('\t')[3]            
            language=language.replace('\n','')
            
           
                
                
            if os.path.exists('./'+language+'/'+projectName):
                continue
            
            
            if language not in 'python':
                continue
            
            
            projectGithubLink=projectGithubLink.replace('\n','')
            if projectGithubLink in '':
                continue
                

            os.system('mkdir '+projectName)
            os.chdir(projectName)
        
            gitName=projectGithubLink.split('/')[-1]
            gitName=gitName.split('.')[0]            
            os.system('git clone '+projectGithubLink)
            if not os.path.exists('./'+gitName):
                continue
            os.chdir(gitName)
            
            
            if not os.path.exists('../count.txt'):            
                collectOSSFUZZCommitID(projectName,projectGithubLink,gitName)                        
            
            processOSSFuzz(projectName,projectGithubLink,gitName,'npe9SFf_FdySwaopaRH1AzoxNjkwNTk3NTU2')
            includeBuggyFixCode(projectName,projectGithubLink,gitName,'buggy')
            analyzeBugs(projectName,projectGithubLink,gitName,language)
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    