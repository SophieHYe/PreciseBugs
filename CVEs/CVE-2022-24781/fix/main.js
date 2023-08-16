/* eslint-disable no-unused-vars */

// eslint-disable-next-line no-undef
const socket = io();

var MouseSelectedText = false;
var MouseSelectedBox = false;
var loaded = false;

var questionPopupToRespond = false;

const Board1 = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
const Board2 = [0, 0, 0, 0, 0, 0, 0, 0, 0, 11];
const Board3 = [21, 20, 19, 18, 17, 16, 15, 14, 13, 12];
const Board4 = [22, 0, 0, 0, 0, 0, 0, 0, 0, 0];
const Board5 = [23, 0, 27, 28, 29, 0, 33, 34, 35, 0];
const Board6 = [24, 25, 26, 0, 30, 31, 32, 0, 36, 37];

function BoxWrapper(Name) {
  if (!MouseSelectedText && !MouseSelectedBox) {
    setTimeout(() => {
      if (!MouseSelectedText && !MouseSelectedBox) {
        document.getElementsByClassName(Name)[0].id = 'CopyTextSpeechBoxDisapear';
        setTimeout(() => {
          if (!MouseSelectedText && !MouseSelectedBox) {
            document.getElementsByClassName('CopyText')[0].textContent = 'Copiar Código';
            document.getElementsByClassName(Name)[0].id = '';
            document.getElementsByClassName(Name)[0].style.display = 'none';
          } else {
            document.getElementsByClassName('CopyText')[0].textContent = 'Copiar Código';
            document.getElementsByClassName(Name)[0].style.display = 'block';
            document.getElementsByClassName(Name)[0].id = '';
          }
        }, 300);
      } else {
        document.getElementsByClassName('CopyText')[0].textContent = 'Copiar Código';
        document.getElementsByClassName(Name)[0].style.display = 'block';
        document.getElementsByClassName(Name)[0].id = '';
      }
    }, 500);
  } else {
    document.getElementsByClassName(Name)[0].style.display = 'block';
    document.getElementsByClassName(Name)[0].id = '';
  }
}

function copyToClipboard(text) {
  var dummy = document.createElement('textarea');
  // to avoid breaking orgain page when copying more words
  // cant copy when adding below this code
  // dummy.style.display = 'none'
  document.body.appendChild(dummy);
  // Be careful if you use texarea. setAttribute('value', value), which works with "input" does not work with "textarea". – Eduard
  dummy.value = text;
  dummy.select();
  document.execCommand('copy');
  document.body.removeChild(dummy);
}

var CurrentRoom = '';
var UserName = '';
var UserId = '';

var playingPlayers = [];

function Loaded() {
  if (loaded) return;

  socket.on('Win', () => {
    document.getElementsByClassName('Winning')[0].style.display = 'block';
  });

  socket.on('Lose', () => {
    document.getElementsByClassName('Losing')[0].style.display = 'block';
  });

  socket.on('NewUser', (Data) => {
    Data = JSON.parse(Data);
    if (!document.getElementById(Data.EnteredUserId)) {
      CreateUsers(Data.EnteredUserId);
    }
    AddUserPawn();
    const Playrs = [...document.getElementsByClassName('Players')[0].children];
    Playrs[0].style.webkitTextStroke = '1px #FFF';
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
  });

  socket.on('UserNameInformation', (Data) => {
    console.log('Received UserNameInformation: ' + Data);
    Data = JSON.parse(Data);
    const Players = [...document.getElementsByClassName('Players')[0].children];
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
    Data.NickName = Data.NickName.split('>').join(' ');
    Data.NickName = Data.NickName.split('<').join(' ');
    Data.NickName = Data.NickName.split('/').join(' ');
    var userFounded = false;
    console.log(Data.UserId);
    Players.forEach((element) => {
      if (element.id === Data.UserId) {
        userFounded = true;
        if (playingPlayers.find((object) => { return object.id === Data.UserId; })) {
          const player = playingPlayers.find((object) => { return object.id === Data.UserId; });
          playingPlayers[player.index].name = Data.NickName;
          console.log('Updated user on the list: ' + JSON.stringify(playingPlayers));
        } else {
          playingPlayers.push({
            id: Data.UserId,
            name: Data.NickName,
            index: playingPlayers.length,
          });
          console.log('Added new user to the list: ' + JSON.stringify(playingPlayers));
        }
        element.innerHTML = Data.UserId === UserId ? `Você (${Data.NickName})` : Data.NickName;
      }
    });
    if (!userFounded) {
      playingPlayers.push({
        id: Data.UserId,
        name: Data.NickName,
        index: playingPlayers.length,
      });
      console.log('Created new user to the list: ' + JSON.stringify(playingPlayers));
      console.log(Data.UserId);
      const Player = document.createElement('div');
      Player.className = 'PlayerWrapper';
      Player.id = Data.UserId;
      Player.innerHTML = Data.UserId === UserId ? `Você (${Data.NickName})` : Data.NickName;
      document.getElementsByClassName('Players')[0].appendChild(Player);
    }
  });

  socket.on('UserLeave', (Data) => {
    Data = JSON.parse(Data);
    if (document.getElementById(Data.UserLeaved)) {
      document.getElementById(Data.UserLeaved).remove();
    }
    if (playingPlayers.find((object) => { return object.id === Data.UserId; })) {
      playingPlayers.splice(playingPlayers.find((object) => { return object.id === Data.UserId; }).index, 1);
    }
    document.getElementsByClassName('Players')[0].children[0].style.webkitTextStroke = '1px #FFF';
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
    AddUserPawn();
  });

  socket.on('FullRoom', () => {
    document.getElementsByClassName('MenuBackground')[0].style.display = 'block';
    document.getElementsByClassName('ConnectingScreen')[0].style.display = 'none';
    document.getElementsByClassName('ProgressProgressBar')[0].style.width = '0vw';
    document.getElementsByClassName('Menus')[0].style.display = 'block';
    document.getElementsByClassName('Game')[0].style.display = 'none';
    document.getElementsByClassName('UserNameInput')[0].id = '';
    document.getElementsByClassName('UserNameInput')[0].style.display = 'none';
    document.getElementsByClassName('RoomFulled')[0].style.display = 'block';
    CurrentRoom = '';
    UserName = '';
    const Playrs = [...document.getElementsByClassName('Players')[0].children];
    Playrs.forEach((element, index) => {
      element.remove();
    });
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
    playingPlayers = [];
  });

  socket.on('RoomNotExists', () => {
    document.getElementsByClassName('MenuBackground')[0].style.display = 'block';
    document.getElementsByClassName('ConnectingScreen')[0].style.display = 'none';
    document.getElementsByClassName('ProgressProgressBar')[0].style.width = '0vw';
    document.getElementsByClassName('Menus')[0].style.display = 'block';
    document.getElementsByClassName('Game')[0].style.display = 'none';
    document.getElementsByClassName('UserNameInput')[0].id = '';
    document.getElementsByClassName('UserNameInput')[0].style.display = 'none';
    document.getElementsByClassName('RoomNotFounded')[0].style.display = 'block';
    CurrentRoom = '';
    UserName = '';
    const Playrs = [...document.getElementsByClassName('Players')[0].children];
    Playrs.forEach((element, index) => {
      element.remove();
    });
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
    playingPlayers = [];
    document.getElementById('C0').style.top = 0 + 'vw';
    document.getElementById('C0').style.left = 0 + 'vw';
    document.getElementById('C1').style.top = 0 + 'vw';
    document.getElementById('C1').style.left = 0 + 'vw';
    document.getElementById('C2').style.top = 0 + 'vw';
    document.getElementById('C2').style.left = 0 + 'vw';
    document.getElementById('C3').style.top = 0 + 'vw';
    document.getElementById('C3').style.left = 0 + 'vw';
  });

  document.getElementsByClassName('RoomForm')[0].addEventListener('submit', PlayRoomIdBlockAndExecute);
  document.getElementsByClassName('QuestionFormInput')[0].addEventListener('submit', ResponseQ);

  socket.on('SetID', (id) => {
    UserId = id;
    console.log('My new ID is: ' + id);
  });

  socket.on('Reconnecting', (Data) => {
    Data = JSON.parse(Data);

    if (Data.RoomId !== CurrentRoom) return;

    const Players = [...document.getElementsByClassName('Players')[0].children];

    Players.forEach((element) => {
      if (element.id === Data.UserId) {
        element.innerHTML = 'Reconnecting...';
      }
    });
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
  });

  socket.on('reconnect', () => {
    console.log('Reconnect!');
    socket.emit('Reconnector', JSON.stringify({ oldUserId: UserId }));
  });

  socket.on('ChangeID', (Data) => {
    if (UserId === '') return;
    console.log('Changing ID... ' + Data);
    Data = JSON.parse(Data);
    const Players = [...document.getElementsByClassName('Players')[0].children];
    Players.forEach((element, index) => {
      if (element.id === Data.oldUserId) {
        element.id = Data.newUserId;
        const player = playingPlayers.find((object) => { return object.id === Data.oldUserId; });
        if (player) {
          playingPlayers[player.index].id = Data.newUserId;
          console.log('changed id actual user NewId:' + Data.newUserId + ' OldId:' + Data.oldUserId + ' Index:' + player.index + ' Full Object:' + JSON.stringify(playingPlayers[player.index]));
        }
      }
    });
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
    UserId = Data.newUserId;
  });

  socket.on('UpdateUserId', (Data) => {
    Data = JSON.parse(Data);
    const Players = [...document.getElementsByClassName('Players')[0].children];
    Players.forEach((element, index) => {
      if (element.id === Data.OldId) {
        element.id = Data.NewId;
        const checkPlayer = playingPlayers.find((object) => { return object.id === Data.OldId; });
        if (!checkPlayer) return;
        playingPlayers[checkPlayer.index].id = Data.NewId;
        console.log('Updated some user ID: ' + Data.NewId + ' OldId:' + Data.OldId + ' Index:' + checkPlayer.index + ' Full Object:' + JSON.stringify(playingPlayers[checkPlayer.index]));
      }
    });
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
  });

  socket.on('ConnectionFailed', () => {
    document.getElementsByClassName('ErrorScreen')[0].style.display = 'block';
    CurrentRoom = '';
    UserName = '';
  });

  socket.on('DiceRolled', (Data) => {
    Data = JSON.parse(Data);
    if (Data.UserId === UserId) {
      document.getElementsByClassName('DiceNumber')[0].style.display = 'block';
      document.getElementsByClassName('DiceNumber')[0].innerHTML = Data.DiceResult.toString();
    }
    let UserIndex = 0;
    const Playrs = [...document.getElementsByClassName('Players')[0].children];
    Playrs.forEach((element, index) => {
      if (element.id === Data.UserId) {
        UserIndex = index;
      }
    });
    Data.UserPosition += 1;
    var Collumn = 0;
    var Row = 0;
    if (Board1.indexOf(Data.UserPosition) > -1) {
      Collumn = 1;
      Row = Board1.indexOf(Data.UserPosition);
    }
    if (Board2.indexOf(Data.UserPosition) > -1) {
      Collumn = 2;
      Row = Board2.indexOf(Data.UserPosition);
    }
    if (Board3.indexOf(Data.UserPosition) > -1) {
      Collumn = 3;
      Row = Board3.indexOf(Data.UserPosition);
    }
    if (Board4.indexOf(Data.UserPosition) > -1) {
      Collumn = 4;
      Row = Board4.indexOf(Data.UserPosition);
    }
    if (Board5.indexOf(Data.UserPosition) > -1) {
      Collumn = 5;
      Row = Board5.indexOf(Data.UserPosition);
    }
    if (Board6.indexOf(Data.UserPosition) > -1) {
      Collumn = 6;
      Row = Board6.indexOf(Data.UserPosition);
    }
    if (Collumn < 0) {
      Collumn = 0;
    }
    if (Row < 0) {
      Row = 0;
    }
    document.getElementById('C' + UserIndex.toString()).style.top = (Math.floor(Collumn - 1) * 7) + 'vw';
    document.getElementById('C' + UserIndex.toString()).style.left = (Math.floor(Row) * 7) + 'vw';
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
  });
  socket.on('QuestionToAnswer', (Data) => {
    questionPopupToRespond = true;
    Data = JSON.parse(Data);
    document.getElementsByClassName('QuestionInput')[0].id = 'popup';
    document.getElementsByClassName('QuestionTextInput')[0].value = '';
    setTimeout(() => { document.getElementsByClassName('QuestionInput')[0].id = ''; }, 300);
    document.getElementsByClassName('QuestionInput')[0].style.display = 'block';
    document.getElementsByClassName('SideA')[0].innerHTML = Data.SideA;
    document.getElementsByClassName('SideB')[0].innerHTML = Data.SideB;
  });

  socket.on('RoomId', (Data) => {
    Data = JSON.parse(Data);
    CurrentRoom = Data.RoomId;
    ProgressbarHandler('ProgressProgressBar', '49.5', '100');
    setTimeout(() => {
      StartTheGame(CurrentRoom);
    }, 1000);
  });

  socket.on('Turn', (Data) => {
    Data = JSON.parse(Data);
    const Playrs = [...document.getElementsByClassName('Players')[0].children];
    Playrs.forEach((element, index) => {
      var userName = playingPlayers.find((object) => { return object.id === element.id; });
      console.log('UserName:' + userName.name);
      console.log('object:' + JSON.stringify(userName));
      if (!userName) { userName = 'Aguardando...'; } else { userName = userName.name; }
      console.log('UserName:' + userName.name);
      if (element.id === UserId) userName = `Você (${userName})`;
      if (index === Data.Turn) {
        element.style.webkitTextStroke = '1px #FFFFFF';
        element.innerHTML = userName + ' <--';
      } else {
        element.style.webkitTextStroke = '0px #0000';
        element.innerHTML = userName;
      }
      if (index === 0) {
        element.style.color = '#5577FF';
      } else if (index === 1) {
        element.style.color = '#FF5555';
      } else if (index === 2) {
        element.style.color = '#FFCC33';
      } else if (index === 3) {
        element.style.color = '#FFAAFF';
      }
    });
    document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
  });

  socket.on('UserNameAlreadyExists', () => {
    console.log('Let\'s change my name!');

    setTimeout(() => {
      document.getElementsByClassName('Rules')[0].style.display = 'none';
      document.getElementsByClassName('UserNameInput')[0].style.display = 'block';
      UserName = '';
    }, 1000);
  });

  loaded = true;
}

function StartGame() {
  document.getElementsByClassName('MenuBackground')[0].style.display = 'none';
  document.getElementsByClassName('ConnectingScreen')[0].style.display = 'block';
  document.getElementsByClassName('ProgressProgressBar')[0].style.width = '0vw';
  CurrentRoom = document.getElementsByClassName('RoomId')[0].value;
  StartTheGame(CurrentRoom);
}

function RoomIdHandler() {
  if (document.getElementsByClassName('RoomId')[0].value.length > 7) {
    document.getElementsByClassName('RoomId')[0].value = document.getElementsByClassName('RoomId')[0].value.slice(0, 7);
  }
  if (document.getElementsByClassName('RoomId')[0].value.length === 7) {
    document.getElementsByClassName('PlayButton')[0].disabled = false;
  } else {
    document.getElementsByClassName('PlayButton')[0].disabled = true;
  }
}

function ProgressbarHandler(Name, Max, Percentage, Time) {
  Time = Time || 2;
  if (Percentage === '100') { Percentage = '98'; }
  const Value = (Percentage / 100) * Max;
  var AnimationTime = 0;
  function Animation() {
    setTimeout(() => {
      if (AnimationTime > 100) { return; }
      AnimationTime += Time;
      if (((AnimationTime / 100) * Value) > document.getElementsByClassName(Name)[0].style.width.toString().substring(0, document.getElementsByClassName(Name)[0].style.width.toString().length - 2)) {
        document.getElementsByClassName(Name)[0].style.width = ((AnimationTime / 100) * Value) + 'vw';
      }
      Animation();
    }, 1);
  }
  Animation();
}

function Credits() {
  document.getElementsByClassName('MenuBackground')[0].style.display = 'none';
  document.getElementsByClassName('CreditsScreen')[0].style.display = 'block';
}

function BackToMainMenu(Page) {
  if (Page === 'Credits') {
    document.getElementsByClassName('CreditsScreen')[0].style.display = 'none';
  }
  document.getElementsByClassName('MenuBackground')[0].style.display = 'block';
}

function CreateGame() {
  document.getElementsByClassName('MenuBackground')[0].style.display = 'none';
  document.getElementsByClassName('ConnectingScreen')[0].style.display = 'block';
  document.getElementsByClassName('ProgressProgressBar')[0].style.width = '0vw';
  socket.emit('GenerateRoom', JSON.stringify({ UserId: UserId }));
  ProgressbarHandler('ProgressProgressBar', '49.5', '75');
}

function UsernameHandler() {
  if (document.getElementsByClassName('UserTextInput')[0].value.length > 16) {
    document.getElementsByClassName('UserTextInput')[0].value = document.getElementsByClassName('UserTextInput')[0].value.slice(0, 16);
  }
  if (document.getElementsByClassName('UserTextInput')[0].value.length > 0) {
    document.getElementsByClassName('UserTextSubmit')[0].disabled = false;
  } else {
    document.getElementsByClassName('UserTextSubmit')[0].disabled = true;
  }
}

function UserNameSubmit(event) {
  event.preventDefault();
  UserName = document.getElementsByClassName('UserTextInput')[0].value;
  socket.emit('RegisterUserName', JSON.stringify({ RoomId: CurrentRoom, UserId: UserId, UserName: UserName }));
  document.getElementsByClassName('UserNameInput')[0].id = 'popup-close';
  setTimeout(() => {
    document.getElementsByClassName('UserNameInput')[0].id = '';
    document.getElementsByClassName('UserNameInput')[0].style.display = 'none';
    document.getElementsByClassName('Rules')[0].style.display = 'block';
    document.getElementsByClassName('Rules')[0].id = 'popup';
  }, 300);
}

function StartTheGame(RoomID) {
  function WaitLoad() {
    if (loaded === false) {
      setTimeout(() => { WaitLoad(); }, 2500);
    } else {
      socket.emit('RegisterUser', JSON.stringify({ RoomId: RoomID }));
      document.getElementsByClassName('userInput')[0].addEventListener('submit', UserNameSubmit);
      document.getElementsByClassName('Menus')[0].style.display = 'none';
      document.getElementsByClassName('Game')[0].style.display = 'block';
      document.getElementsByClassName('RoomIdText')[0].innerHTML = RoomID;
      document.getElementsByClassName('UserNameInput')[0].id = 'popup';
      document.getElementsByClassName('UserNameInput')[0].style.display = 'block';
      document.getElementsByClassName('UserTextInput')[0].value = UserName;
      setTimeout(() => { document.getElementsByClassName('UserNameInput')[0].id = ''; }, 300);
    }
  }
  WaitLoad();
}

function CreateUsers(id) {
  document.getElementsByClassName('Players')[0].innerHTML += '<div id="' + id + '" class="PlayerWrapper">Aguardando...</div>';
}

function PlayRoomIdBlockAndExecute(event) {
  event.preventDefault();
  StartGame();
}

function RunDice() {
  socket.emit('RunDice', JSON.stringify({ RoomId: CurrentRoom, UserId: UserId }));
}

function AddUserPawn() {
  const Players = [...document.getElementsByClassName('Players')[0].children];
  if (Players.length === 2) {
    document.getElementById('C0').style.display = 'block';
    document.getElementById('C1').style.display = 'block';
    document.getElementById('C2').style.display = 'none';
    document.getElementById('C3').style.display = 'none';
  } else if (Players.length === 3) {
    document.getElementById('C0').style.display = 'block';
    document.getElementById('C1').style.display = 'block';
    document.getElementById('C2').style.display = 'block';
    document.getElementById('C3').style.display = 'none';
  } else if (Players.length === 4) {
    document.getElementById('C0').style.display = 'block';
    document.getElementById('C1').style.display = 'block';
    document.getElementById('C2').style.display = 'block';
    document.getElementById('C3').style.display = 'block';
  } else {
    document.getElementById('C0').style.display = 'block';
    document.getElementById('C1').style.display = 'none';
    document.getElementById('C2').style.display = 'none';
    document.getElementById('C3').style.display = 'none';
  }
  document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
}

function HipotenusaResulver() {
  if (document.getElementsByClassName('QuestionTextInput')[0].value.length > 0 && !isNaN(document.getElementsByClassName('QuestionTextInput')[0].value)) {
    document.getElementsByClassName('QuestionTextSubmit')[0].disabled = false;
  } else {
    document.getElementsByClassName('QuestionTextSubmit')[0].disabled = true;
  }
}

function ResponseQ(event) {
  event.preventDefault();
  questionPopupToRespond = false;
  if (!document.getElementsByClassName('QuestionTextSubmit')[0].disabled) {
    document.getElementsByClassName('QuestionInput')[0].id = 'popup-close';
    setTimeout(() => {
      document.getElementsByClassName('QuestionInput')[0].id = '';
      if (questionPopupToRespond) { return; }
      document.getElementsByClassName('QuestionInput')[0].style.display = 'none';
    }, 300);
    socket.emit('QuestionResponse', JSON.stringify({ RoomId: CurrentRoom, UserId: UserId, Answer: document.getElementsByClassName('QuestionTextInput')[0].value }));
  }
}

function Win() {
  document.getElementsByClassName('Winning')[0].style.display = 'none';
  document.getElementsByClassName('MenuBackground')[0].style.display = 'block';
  document.getElementsByClassName('ConnectingScreen')[0].style.display = 'none';
  document.getElementsByClassName('ProgressProgressBar')[0].style.width = '0vw';
  document.getElementsByClassName('Menus')[0].style.display = 'block';
  document.getElementsByClassName('Game')[0].style.display = 'none';
  document.getElementsByClassName('UserNameInput')[0].id = '';
  document.getElementsByClassName('UserNameInput')[0].style.display = 'none';
  document.getElementsByClassName('RoomFulled')[0].style.display = 'none';
  CurrentRoom = '';
  UserName = '';
  const Playrs = [...document.getElementsByClassName('Players')[0].children];
  Playrs.forEach((element, index) => {
    element.remove();
  });
  document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
  playingPlayers = [];
  document.getElementById('C0').style.top = 0 + 'vw';
  document.getElementById('C0').style.left = 0 + 'vw';
  document.getElementById('C1').style.top = 0 + 'vw';
  document.getElementById('C1').style.left = 0 + 'vw';
  document.getElementById('C2').style.top = 0 + 'vw';
  document.getElementById('C2').style.left = 0 + 'vw';
  document.getElementById('C3').style.top = 0 + 'vw';
  document.getElementById('C3').style.left = 0 + 'vw';
}

function Lose() {
  document.getElementsByClassName('Losing')[0].style.display = 'none';
  document.getElementsByClassName('MenuBackground')[0].style.display = 'block';
  document.getElementsByClassName('ConnectingScreen')[0].style.display = 'none';
  document.getElementsByClassName('ProgressProgressBar')[0].style.width = '0vw';
  document.getElementsByClassName('Menus')[0].style.display = 'block';
  document.getElementsByClassName('Game')[0].style.display = 'none';
  document.getElementsByClassName('UserNameInput')[0].id = '';
  document.getElementsByClassName('UserNameInput')[0].style.display = 'none';
  document.getElementsByClassName('RoomFulled')[0].style.display = 'none';
  CurrentRoom = '';
  UserName = '';
  const Playrs = [...document.getElementsByClassName('Players')[0].children];
  Playrs.forEach((element, index) => {
    element.remove();
  });
  document.getElementsByClassName('PlayerInTheRoomHeader')[0].innerHTML = `Jogadores na sala: (${playingPlayers.length}/4)`;
  playingPlayers = [];
  document.getElementById('C0').style.top = 0 + 'vw';
  document.getElementById('C0').style.left = 0 + 'vw';
  document.getElementById('C1').style.top = 0 + 'vw';
  document.getElementById('C1').style.left = 0 + 'vw';
  document.getElementById('C2').style.top = 0 + 'vw';
  document.getElementById('C2').style.left = 0 + 'vw';
  document.getElementById('C3').style.top = 0 + 'vw';
  document.getElementById('C3').style.left = 0 + 'vw';
}

function CloseRules() {
  document.getElementsByClassName('Rules')[0].id = 'popup-close';
  setTimeout(() => {
    document.getElementsByClassName('Rules')[0].id = '';
    document.getElementsByClassName('Rules')[0].style.display = 'none';
  }, 300);
}
