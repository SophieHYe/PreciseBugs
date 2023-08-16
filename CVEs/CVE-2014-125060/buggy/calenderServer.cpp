#include "calenderServer.h"
#include "serverActions.h"
#include "persistentState.h"
#include "renderPage.h"
#include "objectCache.h"
#include <iostream>
#include <sys/types.h>
#include <sys/socket.h>
#include <netdb.h>
#include <thread>
#include <algorithm>
#include <string>
#include <sstream>
#include <unistd.h>
#include <vector>
#include <map>
#include <boost/algorithm/string.hpp>
  
using namespace std;

int main(int argc, char** argv){
  // Grab the port number from the command line, handling bad inputs.
  int portNum;
  if (argc != 2)
    printUsageAndExit();

  try{
    portNum = stoi(argv[1]);
  } catch(invalid_argument e){
    cerr << "Not a valid number!" << endl;
    printUsageAndExit();
  } catch(out_of_range e){
    cerr << "Port must be between 1 and 65535!" << endl;
    printUsageAndExit();
  }
  if (portNum < 1 || portNum > 65535){
    cerr << "Port must be between 1 and 65535!" << endl;
    printUsageAndExit();
  }

  // Load persistent server state.
  init();

  // Listen for and handle incoming connections.
  serverListen(portNum);

  // Save persistent state. This particular call will actually never
  // happen, since we loop until the user forcefully quits us at
  // present, but luckily we also call shutdown() when we get
  // interrupted with a ctrl-C.
  shutdown();
}

void printUsageAndExit(){
  cerr << "Usage: ./calenderServer portNum, where portNum is the number of the port to listen on. " << endl;
  exit(1);
}

void serverListen(int portNum){
  // Lots of structure set up.
  struct addrinfo hints;
  struct addrinfo* serverInfo;
  char portBuf[6];
  int listenSocket;

  memset(&hints, 0, sizeof(hints));
  hints.ai_family = AF_UNSPEC;
  hints.ai_socktype = SOCK_STREAM;
  hints.ai_flags = AI_PASSIVE;

  // Convert the int port to a string.
  sprintf(portBuf, "%d", portNum);

  // Set up sockets
  if (getaddrinfo(NULL, portBuf, &hints, &serverInfo) != 0){
    cerr << "Problem getting address info!" << endl;
    exit(1);
  }

  if((listenSocket = socket(serverInfo->ai_family, serverInfo->ai_socktype, serverInfo->ai_protocol)) == -1){
    cerr << "Couldn't get a system socket!" << endl;
    exit(1);
  }
  openSockets.push_front(listenSocket);
  int yes = 1;
  setsockopt(listenSocket, SOL_SOCKET, SO_REUSEADDR, &yes, sizeof(yes));

  if(bind(listenSocket, serverInfo->ai_addr, serverInfo->ai_addrlen) == -1){
    cerr << "Couldn't bind the socket!" << endl;
    exit(1);
  }

  freeaddrinfo(serverInfo);

  if(listen(listenSocket, 10) == -1){
    cerr << "Couldn't listen on socket!" << endl;
    exit(1);
  }

  // Loop listening for clients until someone stops us.
  listenLoop(listenSocket);
}

void listenLoop(int listenSocket){
  struct sockaddr_storage clientAddr;
  socklen_t cAddrSize = sizeof(clientAddr);
  list<thread> clientThreads;
  thread ttlThread(expireSessionIDs);
  int clientSocket;

  while(true){
    if((clientSocket = accept(listenSocket, (struct sockaddr *)&clientAddr, &cAddrSize)) == -1){
      cerr << "Failed to accept client." << endl;
      continue;
    }
    openSockets.push_front(clientSocket);
    clientThreads.push_front(thread(handleClient, clientSocket));
  }
}
void expireSessionIDs(){
  while(true){
    for(auto sessionTTLMapping : sessionTTLs){
      if(sessionTTLMapping.second-- <= 0){
	sessionMap.erase(sessionTTLMapping.first);
	sessionTTLs.erase(sessionTTLMapping.first);
      }
    }
    sleep(1);
  }
}

const int BUFFERSIZE = 513;
void handleClient(int clientSocket){
  // Buffer to hold part of the request.
  char requestBuffer[BUFFERSIZE];
  // c++ strings for the request and response.
  string request;
  string response;
  // How many bytes we received this call.
  int bytesReceived;
  // How many bytes we've sent total.
  unsigned bytesSent;
  // How many bytes we've sent this call.
  int sending;

  //while(true){
    // Recieve a single request.
    bytesReceived = recv(clientSocket, requestBuffer, BUFFERSIZE, 0);
    while(bytesReceived == BUFFERSIZE){
      request.append(requestBuffer, bytesReceived);
      bytesReceived = recv(clientSocket, requestBuffer, BUFFERSIZE, MSG_DONTWAIT);
    }
    if (bytesReceived != -1) {
      request.append(requestBuffer, bytesReceived);
    }
    if (bytesReceived == 0) return;
    // Get the response.
    try{
      response = handleRequest(request);
    } catch (exception e){
      cerr << "got an exception, killing client thread..." << endl;
      return;
    }

    // Reply
    bytesSent = 0;
    while(bytesSent < response.length()){
      sending = send(clientSocket, response.c_str(), response.length(), 0);
      if (sending == -1){
	cerr << "Failed to respond to client. " << endl;
	break;
      }
      bytesSent += sending;
    }

    // If the client didn't close the connection, we'll reply
    // again. Otherwise, we'll just try to receive andf then break.
    //}
  close(clientSocket);
  openSockets.remove(clientSocket);
}

string handleRequest(const string& request){
  map<string, string>* reqHeaders = parseRequest(request);
  if ((*reqHeaders)["method"].compare("GET") == 0) {
    string response = handleGet(reqHeaders);
    delete reqHeaders;
    return response;
  } else if ((*reqHeaders)["method"].compare("POST") == 0) {
    string response = handlePost(reqHeaders);
    delete reqHeaders;
    return response;
  } else {
    // should make 404
    string message = "404 Page Not Found";
    cout << "returning error, method is '" << (*reqHeaders)["method"] << "'" << endl;
    string result = getResponseHeader("HTTP/1.1 404 Not Found", reqHeaders, message.size()) + "\r\n" + message;
    delete reqHeaders;
    return result;
  }
}

map<string, string>* parseRequest(const string& request) {
  map<string, string>* headers = new map<string, string>();
  string delim = "\r\n";
  vector<string> lines;
  boost::iter_split(lines, request,
                    boost::first_finder(delim, boost::is_iequal()));
  string method = lines[0].substr(0, lines[0].find(" "));
  (*headers)["method"] = method;
  lines[0] = lines[0].substr(lines[0].find(" ") + 1);
  (*headers)["uri"] = lines[0].substr(0, lines[0].find(" "));
  for (size_t i = 1; i < lines.size(); i++) {
    if (method.compare("POST") == 0 && i == lines.size() - 1) {
      //cout << "params is " << lines[i] << endl;
      (*headers)["params"] = lines[i];
      break;
    } else if (method.compare("GET") == 0 && lines[i].compare("") == 0) {
      break;
    } else if (method.compare("POST") == 0 && lines[i].compare("") == 0) {
      i = lines.size() - 2;
    } else if (method.compare("POST") != 0 || lines[i].compare("") != 0) {
      int mid = lines[i].find(": ");
      string name = lines[i].substr(0, mid);
      string val = lines[i].substr(mid+2, string::npos);
      transform(val.begin(), val.end(), val.begin(), ::tolower);
      (*headers)[name] = val;
    }
  }

  return headers;
}

string handleGet(map<string, string>* reqHeaders) {
  long long sessionId = -1;
  long uid = -1;
  string uri = (*reqHeaders)["uri"];
  string body;
  if (reqHeaders->count("Cookie") != 0) {
    string cookies = (*reqHeaders)["Cookie"];
    string sessionIdString = cookies.substr(cookies.find("=") + 1);
    sessionId = stoll(sessionIdString, nullptr);
    auto usersessionIt = sessionMap.find(sessionId);
    if (usersessionIt == sessionMap.end()){
      cout << "Bad session ID, redirecting to login." << endl;
      body = getLogin();
    } else
      uid = sessionMap[sessionId];
  }
  map<string, string> resHeaders;
  resHeaders["Server"] = "CSE461";
  resHeaders["Content-Type"] = "text/html; charset=UTF-8";

  if (uri.compare("/cal") == 0 && uid == -1) {
    cout << "login page" << endl;
    // login page
    body = getLogin();
  } else if (uri.compare("/cal") == 0 && uid != -1) {
    cout << "cal page" << endl;
    // cal page
    string username = userNameById(uid);
    body = getHeader(username);
    body += getEmptyCalendar();
    body += getFooter();

  } else if (uri.compare("/getEvents") == 0 && uid != -1) {
    //cout << "getevents" << endl;
    // getEvents, has uid
    body = getEventsJson(uid);
    resHeaders["Content-Type"] = "application/json; charset=UTF-8";
  } else {
    // 404
    cout << "error page" << endl;

    string message = "404 Page Not Found";
    return getResponseHeader("HTTP/1.1 404 Not Found", &resHeaders, body.size())
      + "\r\n" + message;
  }
  
  return getResponseHeader("HTTP/1.1 200 OK", &resHeaders, body.size()) + "\r\n" + body;
}

string handlePost(map<string, string>* reqHeaders) {
  long long sessionId = -1;
  long uid = -1;
  string uri = (*reqHeaders)["uri"];
  cout << "handling post" << endl;
  if (reqHeaders->count("Cookie") != 0) {
    string cookies = (*reqHeaders)["Cookie"];
    string sessionIdString = cookies.substr(cookies.find("=") + 1);
    sessionId = stoll(sessionIdString, nullptr);
    uid = sessionMap[sessionId];
  }
  map<string, string> resHeaders;
  resHeaders["Server"] = "CSE461";
  resHeaders["Content-Type"] = "text/html; charset=UTF-8";

  //cout << "uri is " << uri << ", uid is " << uid << endl;

  string body;
  if (uri.compare("/createUser") == 0) {
    cout << "create user" << endl;
    string params = (*reqHeaders)["params"];
    string username = params.substr(9, params.find("&") - 9);
    string password = params.substr(params.find("&") + 10);

    cout << "username is " << username << " password is " << password << endl;

    long newId = makeUser(username, password);
    stringstream bodyStream;

    if (newId == -1) {
      bodyStream << "Error: Could not create user '" << username << "'" << endl;
    } else {
      bodyStream << "Successfully created user '" << username << "'! Please log in." << endl;
    }

    body = bodyStream.str();
  } else if (uri.compare("/login") == 0) {
    if (uid != -1)
      logout(uid);
    cout << "login" << endl;
    string params = (*reqHeaders)["params"];
    string username = params.substr(9, params.find("&") - 9);
    string password = params.substr(params.find("&") + 10);

    cout << "username is " << username << " password is " << password << endl;

    long long sess = login(username, password);

    stringstream bodyStream;
    if (sess == -1) {
      bodyStream << getLogin();
    } else {
      stringstream cookie;
      cookie << "sessionId=" << sess;
      resHeaders["Set-Cookie"] = cookie.str();

      bodyStream << getHeader(username);
      bodyStream << getEmptyCalendar();
      bodyStream << getFooter();
    }


    body = bodyStream.str();
    

  } else if (uri.compare("/createGroup") == 0 && uid != -1) {
    cout << "createGroup" << endl;
    string params = (*reqHeaders)["params"];
    string groupName = params.substr(11);
    replace(groupName.begin(), groupName.end(), '+', ' ');

    stringstream bodyStream;
    long groupId = makeGroup(uid, groupName);
    if (groupId != -1) {
      cout << "made group!" << endl;
      bodyStream << "Successfully created group '" << groupName << "'";
    } else {
      cout << "makeGroup was -1." << endl;
      bodyStream << "Error: could not create group '" << groupName << "'";
    }

    body = bodyStream.str();
    
  } else if (uri.compare("/addToGroup") == 0 && uid != -1) {
    cout << "addToGroup" << endl;
    string params = (*reqHeaders)["params"];
    string groupName = params.substr(11, params.find("&") - 11);
    params = params.substr(params.find("&") + 1);
    string addedName = params.substr(11, params.find("&") - 11);
    params = params.substr(params.find("&") + 1);
    bool admin = params.find("admin") != string::npos;
    long groupId = groupIdByName(groupName);
    long addedId = userIdByName(addedName);

    stringstream bodyStream;
    bool added = addToGroup(uid, addedId, groupId, admin);

    if (added) {
      cout << "added to group!" << endl;
      bodyStream << "Successfully added '" << addedName << "' to '" << groupName << "'";
    } else {
      cout << "Error: could not add '" << addedName << "' to '" << groupName << "'";
      bodyStream << "Error: could not add '" << addedName << "' to '" << groupName << "'";
    }

    body = bodyStream.str();
    
  } else if (uri.compare("/createEvent") == 0 && uid != -1) {
    cout << "createEvent" << endl;
    string params = (*reqHeaders)["params"];
    string eventName = params.substr(5, params.find("&") - 5);
    replace(eventName.begin(), eventName.end(), '+', ' ');
    params = params.substr(params.find("&") + 1);
    string timeString = params.substr(9, params.find("&") - 9);
    params = params.substr(params.find("&") + 1);
    bool withGroup = params.size() > 11;

    cout << "eName: " << eventName << " tS: " << timeString << " wG: " << withGroup << endl;

    stringstream timeStream;
    time_t eventTime;
    timeStream << timeString;
    timeStream >> eventTime;

    long eventId;
    if (withGroup) {
      string groupName = params.substr(11);
      long groupId = groupIdByName(groupName);
      cout << "gName: " << groupName << " gID: " << groupId << endl;

      eventId = makeEvent(uid, eventName, eventTime, groupId, true);
    } else {
      eventId = makeEvent(uid, eventName, eventTime);
    }

    stringstream bodyStream;
    if (eventId != -1) {
      cout << "made event!" << endl;
      bodyStream << "Successfully created event '" << eventName << "'";
    } else {
      cout << "couldn't make event." << endl;
      bodyStream << "Error: could not create event '" << eventName << "'";
    }

    body = bodyStream.str();
    
  } else if (uri.compare("/editEvent") == 0 && uid != -1) {
    cout << "editEvent" << endl;
    string params = (*reqHeaders)["params"];
    long eventId = stol(params.substr(3, params.find("&") - 3), nullptr);
    params = params.substr(params.find("&") + 1);
    string eventName = params.substr(5, params.find("&") - 5);
    params = params.substr(params.find("&") + 1);
    string timeString = params.substr(9, params.find("&") - 9);

    cout << "eID: " << eventId << " eName: " << eventName << " dt: " << timeString << endl;

    stringstream timeStream;
    time_t eventTime;
    timeStream << timeString;
    timeStream >> eventTime;

    renameEvent(uid, eventId, eventName);
    rescheduleEvent(uid, eventId, eventTime);

    stringstream bodyStream;
    cout << "edited event!" << endl;
    bodyStream << "Successfully edited event.";

    body = bodyStream.str();
  } else {
    // 404
    cout << "error page" << endl;

    string message = "404 Page Not Found";
    return getResponseHeader("HTTP/1.1 404 Not Found", &resHeaders, body.size())
      + "\r\n" + message;
  }
  
  return getResponseHeader("HTTP/1.1 200 OK", &resHeaders, body.size()) + "\r\n" + body;
}














