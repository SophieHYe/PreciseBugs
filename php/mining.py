#!/usr/bin/python
import sys, os, time, subprocess,fnmatch, shutil, csv,re, datetime
from time import strptime



def getFlakyTests(bugID):
    print(getFlakyTests)
    print(bugID)

    flakyTestList = []
    with open('../diffs/'+bugID+'/FixExecution-6.txt', 'r') as tests:
        testLines = tests.readlines()
        print(testLines)
        testLines = str(testLines)
        if 'FAILED TEST SUMMARY' in testLines:
            print('FAILED TEST SUMMARY')
            flaky=testLines.split('FAILED TEST SUMMARY')[2]
            print('line19:'+flaky)
            flaky=flaky.split('==================')[0]
            print(flaky)
            flakyTests = flaky.split('\\n')
            for ft in flakyTests:
                if '[' in ft and ']' in ft and 'tests' in ft:
                    testPath = ft.split('[')[1]
                    testPath = testPath.split(']')[0]                      
                    flakyTestList.append(testPath)
    print(len(flakyTestList))                 
    return flakyTestList
                    
    
    





def executeODDFuzzBugs(versionName):
    with open('/home/heye/php/php-src-oss-fuzz.csv', 'r') as bugs:
        lines = bugs.readlines()
        for i in range(109,len(lines),1):
            line = lines[i]
            lst = line.split('\t')
            bugID = lst[0]
            if 'buggy' in versionName:
                parentCommit = lst[2]
            else:
                parentCommit = lst[1]
            testPath=lst[6]
            testName=testPath.split('/')[-1]
            os.chdir('/home/heye/php')
            os.system('rm -rf php-src')
            os.system('git clone https://github.com/php/php-src.git')
            if not os.path.exists('/home/heye/php/php-src'):
                os.chdir('/home/heye/php/')
                os.system('git clone https://github.com/php/php-src.git')
            os.chdir('/home/heye/php/php-src')
            os.system('git reset --hard '+parentCommit)
            
            if 'buggy' in versionName:
                #move tests
                print('cp  /home/heye/php/diffs/'+bugID+'/'+testName +'  '+testPath)
                os.system('cp  /home/heye/php/diffs/'+bugID+'/'+testName +'  '+testPath)    
                
                #remove failing test
                flakyTests = getFlakyTests(bugID)
                for ft in flakyTests:
                    os.system('rm '+ft)
                
                
                
                
                
                
                        
            #remove network related test
            os.system('rm ext/standard/tests/network/bug73594.phpt')
            os.system('./buildconf')
            os.system('./configure --enable-debug')
            os.system('make -j16')
            results = os.popen('timeout 35 make TEST_PHP_ARGS=-j16 test').read()
            os.system('exit()')   
            print(results)

            if 'TEST RESULT SUMMARY' in results:
                results = results.split('TEST RESULT SUMMARY')[1]
                
                
                if 'Tests failed    : ' in results:
                    failingTests = results.split('Tests failed    : ')[1]
                    failingTests = failingTests.split('(')[0]
                    failingTests=failingTests.replace(' ','')
                    print('failingTests:',failingTests)
                    
                    print('parentCommit:',parentCommit)
                    
                    if int(failingTests) > 0 :
                        with open('/home/heye/php/reproducible-php-oss-fuzz.csv', 'a') as behaviorBugs:
                            behaviorBugs.write(line)
                                                                   
                    
                    
                if 'buggy' in versionName:
                    with open('/home/heye/php/diffs/'+bugID+'/BuggyExecution-'+failingTests+'.txt','w') as testInfo:
                        testInfo.write ('BUGGY VERSION RESULT SUMMARY \n\n'+results)
                else:
                    with open('/home/heye/php/diffs/'+bugID+'/FixExecution-'+failingTests+'.txt','w') as testInfo:
                        testInfo.write ('FIXED VERSION RESULT SUMMARY \n\n'+results)


            
    


       
    
def collectOSSFuzzCommmit():
    with open('../php-src-bugs.csv', 'r') as bugs:
        lines = bugs.readlines()
        for line in lines:
            lst = line.split('\t')
            commit = lst[0]
            parentCommit= lst[1]
            
            os.system('git reset --hard '+commit)
            logs = os.popen('git log -1').read()
            if 'oss-fuzz #' in logs:
                fuzzID = logs.split('oss-fuzz #')[1]
                print('fuzzID:'+fuzzID)
                fuzzID = fuzzID.split(' ')[0]                 
                fuzzID=fuzzID.replace('\n','')
                fuzzID='oss-fuzz #'+fuzzID
                print('fuzzID:'+fuzzID)
                
                line = line.replace('\n','\t')
                logs = logs.replace('\n','').replace('  ',' ').replace('\r','')
                
                
                
                
                
                #save diffs
                diffs = os.popen('git diff '+parentCommit+' '+commit).read()
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
                diffName= year+str(month)+date+'-'+commit
                
                
                    
                    
                if '/tests/' in diffs:
                    if '--- /dev/null' in diffs:
                        testPath = diffs.split('--- /dev/null')[1]
                        testPath = testPath.split(' ')[1]
                        testPath=testPath.split('\n')[0]
                        print(testPath)
                        testPath = testPath.replace('b/','')
                        
                        with open('../php-src-oss-fuzz.csv', 'a') as ossbugs:
                            ossbugs.write(diffName+'\t'+line+fuzzID+'\t'+testPath+'\t'+logs+'\n')
                            
                            
                        #move tests
                        os.system('mkdir -p ../diffs/'+diffName)
                        os.system('cp '+testPath+'  ../diffs/'+diffName)
                        
                        #write diffs
                        with open('../diffs/'+diffName+'/'+diffName+'.diff', 'w') as saveDiff:
                            saveDiff.write(diffs)


            
            
    
    
    
    
def collectBugFixCommitID():
    os.system('rm ../php-all-bug-commit.csv')
    logs = os.popen('git log --pretty=format:"%h\t%p\t%cd\t%s" -10000 ').read()
    commits = logs.split('\n')
    for commit in commits:
        print(commit)
        if 'fix' in commit or 'Fix' in commit or 'bug #' in commit or 'Bug #' in commit:
            with open('../php-all-bug-commit.csv', 'a') as csvfile:
                csvfile.write(commit+'\n')





if __name__ == '__main__':
    #we first collect all the bug-fix commits 
    collectBugFixCommitID()
    # we filter the bug-fix commits with oss-fuzz
#     collectOSSFuzzCommmit()
    # we execute the correct (fix) version and collect the test excution information
#     executeODDFuzzBugs('fix')
    # we remove the failing tests in correct (fix) version
#     executeODDFuzzBugs('buggy')
    


        



            

