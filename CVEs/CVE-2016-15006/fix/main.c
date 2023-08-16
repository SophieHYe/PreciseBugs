// Copyright <Pierre-François Monville>
// ===========================================================================
// 									enigmaX
// permet de chiffrer et de déchiffrer tout fichier donné en paramètre
// le mot de passe demandé au début est hashé puis sert de graine pour le PRNG
// le PRNG permet de fournir une clé unique égale à la longueur du fichier à coder
// La clé unique subit un xor avec le mot de passe (le mot de passe est répété 
// autant de fois que nécéssaire). Le fichier subit un xor avec cette clé Puis
// un brouilleur est utilisé, il mélange la table des caractères (ascii)
// en utilisant le PRNG ou en utilisant le keyFile fourni.
//
// Can crypt and decrypt any file given in argument. The password asked is hashed
// to be used as a seed for the PRNG. The PRNG gives a unique key
// which has the same length as the source file. The key is xored with the password 
// (rthe password is repeated as long as necessary). The file is then xored with this
// new key, then a scrambler is used.
// it scrambles the ascii table using the PRNG or the keyFile given.
//
// USAGE : 
//		enigmax [-h | --help] FILE [-s | --standard | -i | --inverted] [KEYFILE]
//
// 		code or decode the given file
//
// 		KEYFILE: 
// 			path to a keyfile that is used to generate the scrambler instead of the password
//
// 		-s --standard : 
// 	 		put the scrambler off
//
//		-i --inverted :
//			inverts the coding/decoding process, first it xors then it scrambles
//
// 		-h --help : 
// 			further help
//
// ===========================================================================

/*
TODO:
crypted folders explorer
graphical interface
special option (multi layer's password, hide extension, randomize the name)
 */


/*
Installation

MAC:
clang -Ofast -fno-unroll-loops main.c -o enigmax

LINUX:
gcc -fno-move-loop-invariants -fno-unroll-loops main.c -o enigmax

you can put the compiled file "enigmax" in your path to use it everywhere
export PATH=$PATH:/PATH/TO/enigmax
write in your ~/.bashrc if you want it to stay after a reboot
*/

/*
	includes
 */
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdint.h>
#include <ctype.h>
#include <time.h>

#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>
#include <errno.h>


/*
	constants
 */
#define BUFFER_SIZE 16384  //16384 //8192


/*
	global variables
 */
static const char *progName;
static const char *fileName;
static char pathToMainFile[1000] = "./";
static char _isADirectory;
static uint64_t seed[2];
static unsigned char scrambleAsciiTables[16][256];
static unsigned char unscrambleAsciiTables[16][256];
static char isCrypting = 1;
static char scrambling = 1;
static char usingKeyFile = 0;
static char isCodingInverted = 0;
static long numberOfBuffer;
static char scramblingTablesOrder[BUFFER_SIZE];

char passPhrase[16384];
uint64_t passIndex = 0;

/*
	-static void usage(int status)
	status : expect EXIT_FAILURE or EXIT_SUCCESS code to choose the output stream

	when the program is typed without arguments in terminal it shows the usage
 */
static void usage(int status)
{
	FILE *dest = (status == 0) ? stdout : stderr;

	if(status == 0){
		fprintf(dest,
			"%s(1)\t\t\tcopyright <Pierre-François Monville>\t\t\t%s(1)\n\nNAME\n\t%s -- crypt or decrypt any data\n\nSYNOPSIS\n\t%s [-h | --help] FILE [-s | --standard | KEYFILE]\n\nDESCRIPTION\n\t(FR) permet de chiffrer et de déchiffrer toutes les données entrées en paramètre le mot de passe demandé au début est hashé puis sert de graine pour le PRNG le PRNG permet de fournir une clé unique égale à la longueur du fichier à coder. La clé unique subit un xor avec le mot de passe (le mot de passe est répété autant de fois que nécéssaire). Le fichier subit un xor avec cette clé Puis un brouilleur est utilisé, il mélange la table des caractères (ascii) en utilisant le PRNG ou en utilisant le keyFile fourni.\n\t(EN) Can crypt and decrypt any data given in argument. The password asked is hashed to be used as a seed for the PRNG. The PRNG gives a unique key which has the same length as the source file. The key is xored with the password (the password is repeated as long as necessary). The file is then xored with this new key, then a scrambler is used. It scrambles the ascii table using the PRNG or the keyFile given\n\nOPTIONS\n\tthe options are as follows:\n\n\t-h | --help\tfurther help.\n\n\t-s | --standard\tput the scrambler on off.\n\n\t-i | --inverted\tinverts the coding/decoding process, first it xors then it scrambles.\n\n\tKEYFILE    \tthe path to a file which will be used to scramble the substitution's tables and choose in which order they will be used instead of the PRNG only (starting at 2.5 ko for the keyfile is great, however not interesting to be too heavy) \n\nEXIT STATUS\n\tthe %s program exits 0 on success, and anything else if an error occurs.\n\nEXAMPLES\n\tthe command:\t%s file1\n\n\tlets you choose between crypting or decrypting then it will prompt for a password that crypt/decrypt file1 as xfile1 in the same folder, file1 is not modified.\n\n\tthe command:\t%s file2 keyfile1\n\n\tlets you choose between crypting or decrypting, will prompt for the password that crypt/decrypt file2, uses keyfile1 to generate the scrambler then crypt/decrypt file2 as file2x in the same folder, file2 is not modified.\n\n\tthe command:\t%s file3 -s\n\n\tlets you choose between crypting or decrypting, will prompt for a password that crypt/decrypt the file without using the scrambler, resulting in using the unique key only.\n", progName, progName, progName, progName, progName, progName, progName, progName);
	} else{
		fprintf(dest,
			"Version : 2.3\nUsage : %s [-h | --help] FILE [-s | --standard | -i | --inverted] [KEYFILE]\nOptions :\n  -h --help :\t\tfurther help\n  -s --standard :\tput the scrambler off\n  -i --inverted :\tinverts the coding/decoding process\n  KEYFILE :\t\tpath to a keyfile that scrambles the substitution's tables and choose they order instead of the PRNG only\n", progName);
	}
	exit(status);
}


/*
	-long ceilRound(float numberToBeRounded)
	returned value : the number rounded (ceil form)

	to prevent from importing all math.h for just one function 
	I had to add it myself
*/
long ceilRound(float numberToBeRounded){
	if (numberToBeRounded - (long) numberToBeRounded > 0)
	{
		return (long) numberToBeRounded + 1;
	}
	return (long) numberToBeRounded;
}


/*
	void clearBuffer()

	empty the buffer	
*/
void clearBuffer()	
{
    int charInBuffer = 0;
    while (charInBuffer != '\n' && charInBuffer != EOF)
    {
        charInBuffer = getchar();
    }
}

/*
	-int readStr(char *str, unsigned long size)
	returned value : the string 'str' of size 'size'

	basicaly, it's doing a fgets but take care of the buffer
*/
int readString(char *string, unsigned long size)
{
    char *EOFPos = NULL;
    
    if(fgets(string, size, stdin) != NULL)	
    {
        EOFPos = strchr(string, '\n'); 
        if(EOFPos != NULL)	
        {
            *EOFPos = '\0';	
        }
        else	
        {
            clearBuffer();	
        }
        return 1;
    }
    else
    {
        clearBuffer();  
        return 0;
    }
}


/*
	-void processTarString(char* string)

	change string placing '\' just before every spaces in order to 
	the tar command to work with files/directories with spaces in their names
*/
char* processTarString(char* string){
	int numberOfSpace = 0;
	char* resultString;

	for (int i = 0; i < strlen(string); ++i)
	{
		if (string[i] == ' ')	
		{
			numberOfSpace++;
		}
	}

	if (numberOfSpace == 0) //just returns the same string basicaly
	{
		resultString = (char*) calloc(1, sizeof(char)* (strlen(string)));
		strcat(resultString, string);
		return resultString;
	}

	resultString = (char*) calloc(1, sizeof(char)* (strlen(string) + numberOfSpace + 1));
	for (int i = 0, j = 0; i < strlen(string); ++i, ++j)
	{
		if (string[i] == ' ')
		{
			resultString[j] = '\\';
			j++;
		}
		resultString[j] = string[i];
	}

	return resultString;
}


/*
	-static inline uint64_t rotationLinearTransformation(const uint64_t seed, int constant)
	seed : the seed which will have the rotation
	constant : number which has to be between 1 and 63
	returned value :  uint64_t number (equivalent to long long but on all OS)

	rotation function for generateNumber
	part of the xoroshiro128+ algorythm :
	http://xoroshiro.di.unimi.it/xoroshiro128plus.c
 */
static inline uint64_t rotationLinearTransformation(const uint64_t seed, int constant) {
	return (seed << constant) | (seed >> (64 - constant));
}

/*
	-uint64_t generateNumber(void)
	returned value :  uint64_t number (equivalent to long long but on all OS)

	random number generator
	with the xoroshiro128+ algorythm which is one of the quickiest PRNG
	it passes the BigCrush test :
	http://xoroshiro.di.unimi.it/xoroshiro128plus.c
 */
uint64_t generateNumber(void) {
	const uint64_t seed0 = seed[0];
	uint64_t seed1 = seed[1];
	const uint64_t result = seed0 + seed1;

	seed1 ^= seed0;
	seed[0] = rotationLinearTransformation(seed0, 55) ^ seed1 ^ (seed1 << 14); // a, b
	seed[1] = rotationLinearTransformation(seed1, 36); // c

	return result;
}

/*
	-uint64_t splitmix64(uint64_t* seed)
	seed : the seed which is modified after each call
	returned value :  uint64_t randomNumber, a random number generated from the seed

	It is a very fast generator passing BigCrush, http://xoroshiro.di.unimi.it/splitmix64.c
	It's here only to populate the seed array "s[2]" for xoroshiro
 */
uint64_t splitmix64(uint64_t* seed) {
	uint64_t randomNumber = (*seed += UINT64_C(0x9E3779B97F4A7C15));
	randomNumber = (randomNumber ^ (randomNumber >> 30)) * UINT64_C(0xBF58476D1CE4E5B9);
	randomNumber = (randomNumber ^ (randomNumber >> 27)) * UINT64_C(0x94D049BB133111EB);
	return randomNumber ^ (randomNumber >> 31);
}


/*
	-uint64_t getHash(char* password)
	password : a string which is the password typed by the user
	returned value :  uint64_t number representing the hash of the string

	simple function that hashes a string into numbers (djb2)
 */
uint64_t getHash(char* password)
{
	uint64_t hash = 5381;
	char c;

	while((c = *password++))
	{
		hash = ((hash << 5) + hash) + c; // hash * 33 + password[i]
	}

	return hash;
}


/*
	-void getSeed(char* password)
	password: the string corresponding to the password given by the user

	this function is here to populate the seed for the PRNG, 
	it hashes the password first then get two 64 bit number from it thanks to splitmix64
	and put the first two outputs into seed[0] and seed[1]
*/
void getSeed(char* password){
	uint64_t hash = getHash(password);

	seed[0] = splitmix64(&hash);
	seed[1] = splitmix64(&hash);
}

/*
	-void scramble(FILE* keyFile)
	keyFile : can be null, if present it passes through all the keyfile to scramble the ascii table

	scramble the ascii table assuring that there is no duplicate
	inspired by the Enigma machine; switching letters but without its weekness,
	here a letter can be switched by itself and it is not possible to know how many letters
	have been switched
 */
void scramble(FILE* keyFile){
	printf("scrambling substitution's tables...\n");
	for (int j = 0; j < 16; ++j)
	{
		char temp = 0;

		for (int i = 0; i < 256; ++i)
		{
			scrambleAsciiTables[j][i] = i;
		}

		if (usingKeyFile){
			int size;
			char extractedString[BUFFER_SIZE] = "";
			unsigned char random256;
			while((size = fread(extractedString, 1, BUFFER_SIZE, keyFile)) > 0){
				for (int i = 0; i < size; ++i)
				{
					random256 = generateNumber() ^ extractedString[i];
					temp = scrambleAsciiTables[j][i%256];
					scrambleAsciiTables[j][i%256] = scrambleAsciiTables[j][random256];
					scrambleAsciiTables[j][random256] = temp;
				}
			}
			rewind(keyFile);
		} else {
			unsigned char random256;
			for (int i = 0; i < 10 * 256; ++i)
			{
				random256 = generateNumber() ^ passPhrase[passIndex];
				passIndex++;
				passIndex %= 16384;
				temp = scrambleAsciiTables[j][i%256];
				scrambleAsciiTables[j][i%256] = scrambleAsciiTables[j][random256];
				scrambleAsciiTables[j][random256] = temp;
			}
		}
	}
	if(usingKeyFile){
		int j = 0;
		char temp[BUFFER_SIZE];
		while(j < BUFFER_SIZE){
			int charactersRead = fread(temp, 1, BUFFER_SIZE, keyFile);
			if(charactersRead == 0){
				rewind(keyFile);
				continue;
			}
			for (int i = 0; i < charactersRead; ++i)
			{
				scramblingTablesOrder[j] = temp[i] & (1+2+4+8);
				j++;
				if(j == BUFFER_SIZE){
					break;
				}
			}
		}
	}
}


/*
	-void unscramble(void)

	this function is here only for optimization
	it inverses the key/value in the scramble ascii table making the backward process instantaneous
 */
void unscramble(){
	for (int j = 0; j < 16; ++j)
	{
		for (int i = 0; i < 256; ++i)
		{
			unsigned char c = scrambleAsciiTables[j][i];
			unscrambleAsciiTables[j][c] = i;
		}
	}
}


/*
	-void codingXOR(char* extractedString, char* keyString, char* xoredString, int bufferLength)
	extractedString : data taken from the source file in a string format
	keyString : a part of the unique key generated by the PRNG in a string format
	xoredString : the result of the xor operation between extractedString and keyString
	bufferLength : the length of the data on which this function is working on

	Apply the mathematical xor function to extractedString and keyString
	if we are coding (isCrypting == 1) then we switche the character from the source file then xor it
	if we are decoding (isCrypting == 0) then we xor the character from the source file then unscramble it
	The scramble table is chosed thanks to the key: We apply a mask to the unique key to catch the last 4 bytes. 
	it gives a number from 0 to 15 that is used to chose the scrambled table. 
	It prevents a frequence analysis of the scrambled file in the event where the unique key has been found. 
	Thus even if you find the seed and by extension, the unique key, you can't apply headers and try to match 
	them to the scrambled file in order to deduce the scramble table. You absolutely need the password.
	we can schemate all the coding/decoding xoring process like this :
	coding : 	original:a -> scramble:x -> xored:?
	decoding : 	xored(?) -> unxored(x) -> unscrambled(a)
 */
void codingXOR(char* extractedString, char* keyString, char* xoredString, int bufferLength)
{
	int i;
	char* tablenumber;

	if(usingKeyFile){
		tablenumber = scramblingTablesOrder;
	}else{
		tablenumber = keyString;
	}

	if(isCodingInverted){
		for (i = 0; i < bufferLength; ++i)
		{
			xoredString[i] = scrambleAsciiTables[tablenumber[i] & (1+2+4+8)][(unsigned char)(extractedString[i] ^ keyString[i])];
		}
	}else{
		for (i = 0; i < bufferLength; ++i)
		{
			xoredString[i] = scrambleAsciiTables[tablenumber[i] & (1+2+4+8)][(unsigned char)extractedString[i]] ^ keyString[i];
		}
	}
}


/*
	-void decodingXOR(char* extractedString, char* keyString, char* xoredString, int bufferLength)
	extractedString : data taken from the source file in a string format
	keyString : a part of the unique key generated by the PRNG in a string format
	xoredString : the result of the xor operation between extractedString and keyString
	bufferLength : the length of the data on which this function is working on

	Here only for optimization purpose to limit the amount of conditions
	Apply the mathematical xor function to extractedString and keyString
	if we are coding (isCrypting == 1) then we switche the character from the source file then xor it
	if we are decoding (isCrypting == 0) then we xor the character from the source file then unscramble it
	we can schemate all the coding/decoding xoring process like this :
	coding : 	original(a) -> scramble(x) -> xored(?)
	decoding : 	xored(?) -> unxored(x) -> unscrambled(a)
 */
void decodingXOR(char* extractedString, char* keyString, char* xoredString, int bufferLength)
{
	int i;
	char* tablenumber;

	if(usingKeyFile){
		tablenumber = scramblingTablesOrder;
	}else{
		tablenumber = keyString;
	}

	if(isCodingInverted){
		for (i = 0; i < bufferLength; ++i)
		{
			xoredString[i] = unscrambleAsciiTables[tablenumber[i] & (1+2+4+8)][(unsigned char)extractedString[i]] ^ keyString[i];
		}
	}else{
		for (i = 0; i < bufferLength; ++i)
		{
			xoredString[i] = unscrambleAsciiTables[tablenumber[i] & (1+2+4+8)][(unsigned char)(extractedString[i] ^ keyString[i])];
		}
	}
}


/*
	-void standardXOR(char* extractedString, char* keyString, char* xoredString, int bufferLength)
	extractedString : data taken from the source file in a string format
	keyString : a part of the unique key generated by the PRNG in a string format
	xoredString : the result of the xor operation between extractedString and keyString
	bufferLength : the length of the data on which this function is working on

	Here only for optimization purpose so that there is the small amount
	of condition possible when encrypt or decrypt
	Apply the mathematical xor function to extractedString and keyString
	if we are coding (isCrypting == 1) then we switche the character from the source file then xor it
	if we are decoding (isCrypting == 0) then we xor the character from the source file then unscramble it
	we can schemate all the coding/decoding xoring process like this :
	coding : 	original(a) -> scramble(x) -> xored(?)
	decoding : 	xored(?) -> unxored(x) -> unscrambled(a)
	but here we don't scramble so it is:
	coding : original(a) -> xored(?)
	decoding: xored(?) -> unxored(a)
 */
void standardXOR(char* extractedString, char* keyString, char* xoredString, int bufferLength)
{
	int i;
	for (i = 0; i < bufferLength; ++i)
	{
		xoredString[i] = extractedString[i] ^ keyString[i];
	}
}


/*
	-int fillbuffer(FILE* mainFile, char* extractedString, char* keyString)
	mainFile : pointer to the file given by the user
	extractedString : will contains the data extracted from the source file in a string format
	keyString : will contains a part of the unique key in a string format
	returned value : the size of the data read

	read a packet of data from the source file
	return the length of the packet which is the buffer size (BUFFER_SIZE)
	it can be less at the final packet (if the file isn't a multiple of the buffer size)

	the keyString is get by generating a random number with the seed and then xoring it 
	with the password itself allowing the key to be really unique and not only one of the 
	2^64 possibilities offered by the seed (uint64_t)
	the password is xoring this way : generateNumber1 ^ passPhrase[0]
									  generateNumber2 ^ passPhrase[1]
									  ...
									  then the index overflows and it returns to 0 again
									  generataNumberX ^ passPhrase[0]
									  ...

	former version (multiply execution time by 5) :
	int fillBuffer(FILE* mainFile, char* extractedString, char* keyString)
	{
		int i = 0;

		while(!feof(mainFile) && i < BUFFER_SIZE)
		{
			char charBuffer = fgetc(mainFile);
			if (feof(mainFile)) break; //special debug for the last character in text files
			extractedString[i] = charBuffer;
			i++;
		}

		return i;
	}
 */
int fillBuffer(FILE* mainFile, char* extractedString, char* keyString)
{
	int charactersRead = fread(extractedString, 1, BUFFER_SIZE, mainFile);

	for (int i = 0; i < charactersRead; ++i)
	{
		keyString[i] = (char)generateNumber() ^ passPhrase[passIndex];
		passIndex++;
		passIndex %= 16384;
	}

	return charactersRead;
}


/*
	-static inline void loadBar(int x, int n, int r, int w)
	currentIteration : the current iteration of the thing that is proccessed
	maximalIteration : the number which represents 100% of the process
	numberOfSteps : number defining how many times the bar updates
	numberOfSegments : diplayed on w segment

	display a loading bar with current percentage, graphic representation, and time remaining
	which update on every new percent by deleting itself to display the updating bar on top
	inspired by Ross Hemsley's code : https://www.ross.click/2011/02/creating-a-progress-bar-in-c-or-any-other-console-app/

 */
static inline void loadBar(int currentIteration, int maximalIteration, int numberOfSteps, int numberOfSegments)
{
	static char firstCall = 1;
	static double elapsedTime;
	double timeTillEnd;
	static time_t startingTime;
	time_t currentTime;

	if(firstCall){
		startingTime = time(NULL);
		firstCall = 0;
	}

    // numberOfSteps defines the number of times the bar updates.
    if ( currentIteration % (maximalIteration/numberOfSteps + 1) != 0 ) return;

    // Calculate the ratio of complete-to-incomplete.
    float ratio = (float) currentIteration / (float) maximalIteration;
    int loadBarCursorPosition = ratio * numberOfSegments;

    // get the clock now
	currentTime = time(NULL);
	// calculate the remaining time
	elapsedTime = difftime(currentTime, startingTime);
	timeTillEnd = elapsedTime * (1.0/ratio - 1.0);

    // Show the percentage.
    printf(" %3d%% [", (int)(ratio*100));

    // Show the loading bar.
    for (int i = 0; i < loadBarCursorPosition; i++)
       printf("=");

    for (int i = loadBarCursorPosition; i < numberOfSegments; i++)
       printf(" ");

    // go back to the beginning of the line.
    // other way (with ANSI CODE) go to previous line then erase it : printf("] %.0f\n\033[F\033[J", timeTillEnd);
    printf("] %.0f        \r", timeTillEnd);
    fflush(stdout);
}


/*
	-void code(FILE* mainFile)
	mainFile : pointer to the file given by the user

	Controller for coding the source file
 */
void code (FILE* mainFile)
{
	int mainFileSize = strlen(fileName);
	char codedFileName[mainFileSize+1];
	char extractedString[BUFFER_SIZE] = "";
	char keyString[BUFFER_SIZE] = "";
	char xoredString[BUFFER_SIZE] = "";
	FILE* codedFile;

	sprintf(codedFileName, "%sx%s", pathToMainFile, fileName);
	// opening the output file
	if ((codedFile = fopen(codedFileName, "w+")) == NULL) {
		perror(codedFileName);
		printf("exiting\n");
		exit(EXIT_FAILURE);
	}

	// starting encryption
	long bufferCount = 0; //keep trace of the task's completion
	printf("starting encryption...\n");
	if (scrambling){
		while(!feof(mainFile))
		{
			int bufferLength = fillBuffer(mainFile, extractedString, keyString);
			codingXOR(extractedString, keyString, xoredString, bufferLength);
			fwrite(xoredString, sizeof(char), bufferLength, codedFile);
			loadBar(++bufferCount, numberOfBuffer, 100, 50);
		}
	} else {
		while(!feof(mainFile))
		{
			int bufferLength = fillBuffer(mainFile, extractedString, keyString);
			standardXOR(extractedString, keyString, xoredString, bufferLength);
			fwrite(xoredString, sizeof(char), bufferLength, codedFile);
			loadBar(++bufferCount, numberOfBuffer, 100, 50);
		}
	}
	// closing the output file
	fclose(codedFile);
	//if the first file was a directory then delete the archive made before crypting
	if (_isADirectory)
	{
		char* tarFile = (char*) calloc (1, sizeof(char) * (strlen(pathToMainFile) + strlen(fileName) + 1));
		strcpy(tarFile, pathToMainFile);
		strcat(tarFile, fileName);
		remove(tarFile);
		free(tarFile);
	}
}


/*
	-void decode(FILE* mainFile)
	mainFile : pointer to the file given by the user

	controller for decoding the source file
 */
void decode(FILE* mainFile)
{
	int mainFileSize = strlen(fileName);
	char decodedFileName[mainFileSize+1];
	char extractedString[BUFFER_SIZE] = "";
	char keyString[BUFFER_SIZE] = "";
	char xoredString[BUFFER_SIZE] = "";
	FILE* decodedFile;

	// Return the file to a unscramble ascii table
	unscramble();

	// naming the file which will be decrypted
	sprintf(decodedFileName, "x%s", fileName);


	// opening the output file
	strcat(pathToMainFile, decodedFileName);
	if ((decodedFile = fopen(pathToMainFile, "w+")) == NULL) {
		perror(decodedFileName);
		printf("exiting\n");
		exit(EXIT_FAILURE);
	}

	// starting decryption
	long bufferCount = 0; //keep trace of the task's completion
	printf("starting decryption...\n");
	if(scrambling){
		while(!feof(mainFile))
		{
			int bufferLength = fillBuffer(mainFile, extractedString, keyString);
			decodingXOR(extractedString, keyString, xoredString, bufferLength);
			fwrite(xoredString, sizeof(char), bufferLength, decodedFile);
			loadBar(++bufferCount, numberOfBuffer, 100, 50);
		}
	} else {
		while(!feof(mainFile))
		{
			int bufferLength = fillBuffer(mainFile, extractedString, keyString);
			standardXOR(extractedString, keyString, xoredString, bufferLength);
			fwrite(xoredString, sizeof(char), bufferLength, decodedFile);
			loadBar(++bufferCount, numberOfBuffer, 100, 50);
		}
	}
	// closing the output file
	fclose(decodedFile);
}



/*
	-int isADirectory(char* path)
	path : string indicated the path of the file/directory
	returned value : 0 or 1

	indicates if the object with this path is a directory or not

*/
int isADirectory(char* path){
	struct stat statStruct;
    int statStatus = stat(path, &statStruct);
    if(-1 == statStatus) {
        if(ENOENT == errno) {
            printf("error: file's path is not correct, one or several directories and or file are missing\n");
        } else {
            perror("stat");
            printf("exiting\n");
            exit(1);
        }
    } else {
        if(S_ISDIR(statStruct.st_mode)) {
        	_isADirectory = 1;
            return 1; //it's a directory
        } else {
        	_isADirectory = 0;
            return 0; //it's not a directory
        }
    }
    printf("exiting\n");
    exit(1);
}



/*
	-int main(int argc, char const* argv[])
	argc : number of arguments passed in the terminal
	argv : pointer to the arguments passed in the terminal
	returned value : 0

 */
int main(int argc, char const *argv[])
{
	FILE* mainFile;
	FILE* keyFile = NULL;

	if ((progName = strrchr(argv[0], '/')) != NULL) {
		++progName;
	} else {
		progName = argv[0];
	}
	if (argc < 2) {
		usage(1);
	} else if(argc >= 5 ) { 
		printf("Error: Too many arguments\n");
		usage(1);
	} else if (strcmp(argv[1], "-h") == 0 || strcmp(argv[1], "--help") == 0) {
		usage(0);
	}

	if (argc >= 3)
	{
		//test if the option -s is present
		if (strcmp(argv[2], "-s") == 0 || strcmp(argv[2], "--standard") == 0){
			scrambling = 0;
			//if there is a keyfile, warns that it will not be used 
			if(argc >= 4){
				if((keyFile = fopen(argv[3], "r")) == NULL){
					perror(argv[3]);
					usage(1);
				}
				printf("Warning: with the -s|--standard option, the keyfile will not bu used\n");
				keyFile = NULL;
			}
		//else the option -i
		} else if (strcmp(argv[2], "-i") == 0 || strcmp(argv[2], "--inverted") == 0){
			isCodingInverted = 1;
			//if i is present, checks if there is a keyfile in the third argument
			if(argc >= 4){
				if((keyFile = fopen(argv[3], "r")) == NULL){
					perror(argv[3]);
					usage(1);
				}
			}
		//if no option is present test if the second argument is a keyfile
		} else if ((keyFile = fopen(argv[2], "r")) == NULL) {
			perror(argv[2]);
			usage(1);
		} else if(keyFile != NULL && argc >= 4){
			printf("Error: Too many arguments\n");
			usage(1);
		}

		if(keyFile != NULL){
			usingKeyFile = 1;
		}
		
	}

	if (argv[1][strlen(argv[1])-1] == '/' && argv[1][strlen(argv[1])-2] == '/')
	{
		printf("error: several trailing '/' in the path of your file\n");
		printf("exiting\n");
		exit(1);
	}

	//outside their scope because we need to free them at the end
	char* tarName = NULL;
	char* dirName = NULL;
	char *copyOfArgv1 = (char*) calloc(1, sizeof(char) * strlen(argv[1]));
	strcpy(copyOfArgv1, argv[1]);
	if (isADirectory(copyOfArgv1)){
		char command[1008] = {'\0'};
		//we don't need that anymore
		printf("regrouping the folder in one file using tar, may be long...");
		fflush(stdout);
		// get the name of the folder in a string and get the path
		if ((fileName = strrchr(argv[1], '/')) != NULL) {
			//if the '/' is the last character in the string, delete it and get the fileName again
			if (strlen(fileName) == 1){
				dirName = (char*) calloc(1, sizeof(char) * (strlen(argv[1]) + 5));
				strcpy(dirName, argv[1]);
				*(dirName+(fileName-argv[1])) = '\0';
				if ((fileName = strrchr(dirName, '/')) != NULL){
					++fileName;
					strncpy(pathToMainFile, dirName, fileName - dirName);
					pathToMainFile[fileName - dirName] = '\0';
				}
				else{
					fileName = dirName;
				}
			}
			else {
				++fileName;
				strncpy(pathToMainFile, argv[1], fileName - argv[1]);
				pathToMainFile[fileName - argv[1]] = '\0';
			}
		}
		else {
			fileName = argv[1];
		}
		// get the full path of the tarFile in a dynamic variable tarName
		tarName = (char*) calloc(1, sizeof(char) * (strlen(fileName) + 5));
		sprintf (tarName, "%s.tar", fileName);

		//all of the following is to make a clean string for the tar commands (taking care of spaces)
		char* cleanFileName       = processTarString((char*)fileName);
		char* cleanPathToMainFile = processTarString(pathToMainFile);
		char* cleanTarName        = processTarString(tarName);
		
		// use of cd to prevent tar to archive all the path architecture 
		// (ex: /usr/myname/my/path/theFolderWeWant/)
		sprintf (command, "cd %s && tar -cf %s %s &>/dev/null", cleanPathToMainFile, cleanTarName, cleanFileName); //&>/dev/null

		//free the temporary strings
		free(cleanPathToMainFile);
		free(cleanTarName);
		free(cleanFileName);

		// make the archive of the folder with tar
		int status;
		if((status = system(command)) != 0){ //if problems when taring
			printf("\nerror: unable to tar your file\n");
			printf("exiting\n");
			exit(1);
		}else{
			printf("\rregrouping the folder in one file using tar... Done          \n");			
		}

		fileName = tarName;

		// trying to open the new archive
		char pathPlusName[strlen(pathToMainFile)+strlen(fileName)];
		sprintf(pathPlusName, "%s%s", pathToMainFile, fileName);
		if ((mainFile = fopen(pathPlusName, "r")) == NULL) {
			perror(pathPlusName);
			printf("exiting\n");
			return EXIT_FAILURE;
		}
	}
	else{
		if ((fileName = strrchr(argv[1], '/')) != NULL) {
			++fileName;
			strncpy(pathToMainFile, argv[1], fileName - argv[1]);		
		} else {
			fileName = argv[1];
		}
		if ((mainFile = fopen(argv[1], "r")) == NULL) {
			perror(argv[1]);
			printf("exiting\n");
			return EXIT_FAILURE;
		}
	}
	free(copyOfArgv1);

	fseek(mainFile, 0, SEEK_END);
	long mainFileSize = ftell(mainFile);
	rewind(mainFile);
	numberOfBuffer = ceilRound((float)mainFileSize / (float)(BUFFER_SIZE));
	if (numberOfBuffer < 1)
	{
		numberOfBuffer = 1;
	}

	char procedureResponse[2]; 
	isCrypting = -1;
	do{
		printf("Crypt(C) or Decrypt(d):");
		readString(procedureResponse, 2);
		printf("\033[F\033[J");
		if (procedureResponse[0] == 'C' || procedureResponse[0] == 'c') {
			isCrypting = 1;
		}
		else if(procedureResponse[0] == 'D' || procedureResponse[0] == 'd'){
			isCrypting = 0;
		}
	}while(isCrypting == -1);
	
	printf("Password:");
	readString(passPhrase, 16383);
	printf("\033[F\033[J");
	getSeed(passPhrase);
	scramble(keyFile);

	if (isCrypting){
		code(mainFile);
	}
	else{
		decode(mainFile);
	}
	printf("Done                                                                  \n");
	fclose(mainFile);

	//we can free (last use in code/decode)
	if(tarName != NULL){
		free(tarName);
	}
	if(dirName != NULL){
		free(dirName);
	}

	return 0;
}
