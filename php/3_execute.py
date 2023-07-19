#!/usr/bin/python
import sys, os, time, subprocess,fnmatch, shutil, csv,re, datetime
from time import strptime



def executeODDFuzzBugs(versionName):
    with open('2-php-oss-fuzz.csv', 'r') as bugs:
        lines = bugs.readlines()
        for i in range(139,len(lines),1):
            line = lines[i]
            lst = line.split('\t')
            bugID = lst[0]
            if 'buggy' in versionName:
                parentCommit = lst[2]
            else:
                parentCommit = lst[1]
            testPath=lst[6]
            testName=testPath.split('/')[-1]
            os.chdir('/home/heye/fuzzbugs/php')
            os.system('rm -rf php-src')
            os.system('git clone https://github.com/php/php-src.git')
            if not os.path.exists('/home/heye/fuzzbugs/php/php-src'):
                os.chdir('/home/heye/fuzzbugs/php/')
                os.system('git clone https://github.com/php/php-src.git')
            os.chdir('/home/heye/fuzzbugs/php/php-src')
            os.system('git reset --hard '+parentCommit)
            
            if 'buggy' in versionName:
                #move tests
                print('cp  /home/heye/fuzzbugs/php/diffs/'+bugID+'/'+testName +'  '+testPath)
                os.system('cp  /home/heye/fuzzbugs/php/diffs/'+bugID+'/'+testName +'  '+testPath)    
                
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
                        with open('/home/heye/fuzzbugs/php/reproducible-php-oss-fuzz.csv', 'a') as behaviorBugs:
                            behaviorBugs.write(line)
                                                                   
                    
                    
                if 'buggy' in versionName:
                    with open('/home/heye/fuzzbugs/php/diffs/'+bugID+'/BuggyExecution-'+failingTests+'.txt','w') as testInfo:
                        testInfo.write ('BUGGY VERSION RESULT SUMMARY \n\n'+results)
                else:
                    with open('/home/heye/fuzzbugs/php/diffs/'+bugID+'/FixExecution-'+failingTests+'.txt','w') as testInfo:
                        testInfo.write ('FIXED VERSION RESULT SUMMARY \n\n'+results)


            
    


       
    



if __name__ == '__main__':
    os.system('git clone https://github.com/php/php-src.git')
    # we execute the correct (fix) version and collect the test excution information
    executeODDFuzzBugs('fix')
    # we remove the failing tests in correct (fix) version
    executeODDFuzzBugs('buggy')