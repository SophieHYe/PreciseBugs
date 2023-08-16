const RoomMaxPersons = 4;
const UsableHomes = 37;

const ActiveRooms = [];
const timeouts = [];
const ids = [];

function cleaner() {
  setTimeout(() => {
    if (ids.length < 1) { return; }
    ActiveRooms.forEach((element, index) => {
      element.Players.forEach((element2, index2) => {
        if (!ids.includes(element2.Id)) {
          console.log('Disconnecting ' + element2.Id);
          console.log(ActiveRooms[index].Players[index2]);
          ActiveRooms[index].Players.splice(index2, 1);
          ActiveRooms[index].Players.forEach((element3) => {
            element3.socket.emit('UserLeave', JSON.stringify({ RoomId: element.Id, UserLeaved: element2.Id }));
          });
          element2.socket.emit('ConnectionFailed');
          element2.socket.disconnect(0);
        }
      });
    });
    ActiveRooms.forEach((element, index) => {
      if (element.Players.length === 0) {
        console.log('Closing Room ' + element.Id);
        ActiveRooms.splice(index, 1);
      }
    });
    console.log('Active Rooms: ' + ActiveRooms.length);
    console.log('Rooms: ', ActiveRooms);
  }, 5000);
}

function getRandomString(length) {
  var randomChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  var result = '';
  for (var i = 0; i < length; i++) {
    result += randomChars.charAt(Math.floor(Math.random() * randomChars.length));
  }
  return result;
}

function reconnect(socket) {
  console.log('Reconnect requested');
  ActiveRooms.forEach((element, index) => {
    element.Players.forEach((element2, index2) => {
      if (element2.socket === socket) {
        const timeouter = {};
        timeouter[element2.Id] = setTimeout(() => {
          console.log('Timeout ' + element2.Id);
          ids.splice(ids.indexOf(element2.Id), 1);
          timeouts.splice(timeouts.indexOf(timeouter), 1);
          cleaner();
        }, 15000);
        timeouts.push(timeouter);
        socket.broadcast.emit('Reconnecting', JSON.stringify({ RoomId: element.Id, UserId: element2.Id }));
      }
    });
  });
  socket.broadcast.emit('reconnect');
  cleaner();
}

function GenerateRoom(socket, Data) {
  if (typeof Data !== 'string') return;
  var RoomScheme = {
    Id: '',
    Players: [],
    Turn: 0,
  };
  Data = JSON.parse(Data);
  var RoomId = getRandomString(7);
  ActiveRooms.forEach((element, index) => {
    if (element.Id === RoomId) {
      GenerateRoom(socket, Data);
    }
  });
  RoomScheme.Id = RoomId;
  ActiveRooms.push(RoomScheme);
  socket.emit('RoomId', JSON.stringify({ RoomId: RoomScheme.Id }));
}

function Updater(socket, Data) {
  if (typeof Data !== 'string') return;
  Data = JSON.parse(Data);
  var UserNewId = getRandomString(12);
  console.log('Updating User ' + Data.oldUserId);

  timeouts.forEach((element, index) => {
    if (element[Data.oldUserId]) {
      console.log('User Reconnected');
      clearTimeout(element[Data.oldUserId]);
      timeouts.splice(index, 1);
    }
  });

  ActiveRooms.forEach((element, index) => {
    element.Players.forEach((element2, index2) => {
      if (element2.Id === Data.oldUserId) {
        ActiveRooms[index].Players[index2].Id = UserNewId;
        element2.socket.emit('ChangeID', JSON.stringify({ RoomId: element.Id, oldUserId: Data.oldUserId, newUserId: UserNewId }));
        ids.push(UserNewId);
        cleaner();
      } else {
        element2.socket.emit('UpdateUserId', JSON.stringify({ RoomId: element.Id, OldId: Data.oldUserId, NewId: UserNewId }));
      }
    });
  });
}

function verifyer(socket, executer, Data, UserIn) {
  if (UserIn === undefined) {
    UserIn = true;
  }
  if (typeof Data !== 'string') {
    socket.emit('ConnectionFailed');
    socket.disconnect(0);
    socket.destroy();
    return;
  }
  Data = JSON.parse(Data);
  var RoomContainsUser = false;
  var RoomExists = false;
  ActiveRooms.forEach((element, index) => {
    if (element.Id === Data.RoomId) {
      RoomExists = true;
      ActiveRooms[index].Players.forEach((element2, index2) => {
        if (element2.Id === Data.UserId) {
          RoomContainsUser = true;
        }
      });
    }
  });
  if ((RoomContainsUser && UserIn) || (RoomExists && !UserIn)) {
    executer(socket, Data);
  } else if (RoomExists && UserIn) {
    socket.emit('ConnectionFailed');
  } else {
    socket.emit('RoomNotExists');
  }
}

function LogUserIn(socket, Data) {
  var UserId;
  do {
    UserId = getRandomString(12);
  } while (ids.includes(UserId));

  var isRoomFull = false;

  ActiveRooms.forEach((element, index) => {
    if (element.Id === Data.RoomId) {
      if (ActiveRooms[index].Players.length >= RoomMaxPersons) {
        isRoomFull = true;
        return socket.emit('FullRoom');
      }
    }
  });

  if (isRoomFull) return;

  var RoomIndex = 0;

  ActiveRooms.forEach((element, index) => {
    if (element.Id === Data.RoomId) {
      RoomIndex = index;
      ActiveRooms[index].Players.forEach((element2) => {
        element2.socket.emit('NewUser', JSON.stringify({ RoomId: element.Id, EnteredUserId: UserId }));
        socket.emit('NewUser', JSON.stringify({ RoomId: element.Id, EnteredUserId: element2.Id }));
        socket.emit('UserNameInformation', JSON.stringify({ RoomId: element.Id, UserId: element2.Id, NickName: element2.Name }));
      });
    }
  });

  const PlayerScheme = {
    Id: '',
    socket,
    Name: '',
    Position: 0,
    NextPosition: 0,
    QSideA: 0,
    QSideB: 0,
    QSideH: 0,
    QSheld: false,
  };

  PlayerScheme.Id = UserId;
  PlayerScheme.socket = socket;
  ActiveRooms[RoomIndex].Players.push(PlayerScheme);

  ids.push(UserId);

  socket.emit('SetID', UserId);

  socket.emit('NewUser', JSON.stringify({ RoomId: Data.RoomId, EnteredUserId: UserId }));
}

function RegisterUserName(socket, Data) {
  var userName = Data.UserName.split('>').join(' ').split('<').join(' ').split('/').join(' ');
  if (userName.length > 16) userName = userName.slice(0, 16);
  if (userName.toLowerCase().includes('você')) userName = '~' + userName;
  ActiveRooms.forEach((element, index) => {
    if (element.Id === Data.RoomId) {
      ActiveRooms[index].Players.forEach((element2, index2) => {
        if (element2.Name.toLowerCase() === userName.toLowerCase()) {
          console.log('UserName already exists');
          return socket.emit('UserNameAlreadyExists');
        } else if (element2.Id === Data.UserId) {
          ActiveRooms[index].Players[index2].Name = userName;
        }
        console.log('Sending UserName to user: ' + element2.Id + ' ' + userName);
        element2.socket.emit('UserNameInformation', JSON.stringify({ RoomId: element.Id, UserId: Data.UserId, NickName: userName }));
      });
    }
  });
}

function RunDice(socket, Data) {
  var itsUserTurn = false;
  var RoomIndex;
  var UserIndex;
  ActiveRooms.forEach((element, RI) => {
    if (element.Id === Data.RoomId) {
      element.Players.forEach((player, index) => {
        if (player.Id === Data.UserId) {
          if (index === element.Turn) {
            itsUserTurn = true;
            RoomIndex = RI;
            UserIndex = index;
          }
        }
      });
    }
  });
  if (itsUserTurn && !ActiveRooms[RoomIndex].Players[UserIndex].QSheld) {
    const DiceResult = Math.floor(Math.random() * ((6 - 1) + 1) + 1);
    // const DiceResult = (18 * 2) + 1; // Debug
    ActiveRooms[RoomIndex].Players[UserIndex].NextPosition = ActiveRooms[RoomIndex].Players[UserIndex].Position + DiceResult;
    ActiveRooms[RoomIndex].Players.forEach((element) => {
      element.socket.emit('DiceRolled', JSON.stringify({ RoomId: Data.RoomId, UserId: Data.UserId, DiceResult: DiceResult, UserPosition: ActiveRooms[RoomIndex].Players[UserIndex].NextPosition }));
    });
    GenerateQuestion(socket, Data);
  }
}

function GenerateQuestion(socket, Data) {
  var HSideA;
  var HSideB;
  var HSideH;

  function Square(Number) {
    return Number * Number;
  }

  function GenerateHypertenuse() {
    const SideA = Math.floor(Math.random() * ((100 - 2) + 2) + 1);
    const SideB = Math.floor(Math.random() * ((100 - 2) + 2) + 1);
    if (Math.sqrt(Square(SideA) + Square(SideB)) === Math.floor(Math.sqrt(Square(SideA) + Square(SideB)))) {
      HSideA = SideA;
      HSideB = SideB;
      HSideH = Math.sqrt(Square(SideA) + Square(SideB));
    } else {
      GenerateHypertenuse();
    }
  }
  GenerateHypertenuse();
  var GRoomIndex;
  var GUserIndex;
  ActiveRooms.forEach((element, RI) => {
    if (element.Id === Data.RoomId) {
      element.Players.forEach((player, index) => {
        if (player.Id === Data.UserId) {
          GRoomIndex = RI;
          GUserIndex = index;
        }
      });
    }
  });
  ActiveRooms[GRoomIndex].Players[GUserIndex].QSideA = HSideA;
  ActiveRooms[GRoomIndex].Players[GUserIndex].QSideB = HSideB;
  ActiveRooms[GRoomIndex].Players[GUserIndex].QSideH = HSideH;
  socket.emit('QuestionToAnswer', JSON.stringify({ RoomId: Data.RoomId, UserId: Data.UserId, SideA: HSideA, SideB: HSideB }));
  ActiveRooms[GRoomIndex].Players[GUserIndex].QSheld = true;
}

function QR(socket, Data) {
  var itsUserTurn = false;
  var RoomIndex;
  var UserIndex;
  ActiveRooms.forEach((element, RI) => {
    if (element.Id === Data.RoomId) {
      element.Players.forEach((player, index) => {
        if (player.Id === Data.UserId) {
          if (index === element.Turn) {
            itsUserTurn = true;
            RoomIndex = RI;
            UserIndex = index;
          }
        }
      });
    }
  });
  if (itsUserTurn && ActiveRooms[RoomIndex].Players[UserIndex].QSheld) {
    if (!isNaN(Number(Data.Answer)) && Number(Data.Answer) === ActiveRooms[RoomIndex].Players[UserIndex].QSideH) {
      // The User Answer Correct
      ActiveRooms[RoomIndex].Players[UserIndex].Position = ActiveRooms[RoomIndex].Players[UserIndex].NextPosition;
      ActiveRooms[RoomIndex].Players.forEach((element) => {
        element.socket.emit('DiceRolled',
          JSON.stringify({ RoomId: Data.RoomId, UserId: Data.UserId, DiceResult: '', UserPosition: ActiveRooms[RoomIndex].Players[UserIndex].NextPosition }));
      });
      ActiveRooms[RoomIndex].Turn += 1;
      if (ActiveRooms[RoomIndex].Turn > ActiveRooms[RoomIndex].Players.length - 1) {
        ActiveRooms[RoomIndex].Turn = 0;
      }
    } else {
      // He failed
      ActiveRooms[RoomIndex].Players[UserIndex].NextPosition = ActiveRooms[RoomIndex].Players[UserIndex].Position;
      ActiveRooms[RoomIndex].Players.forEach((element) => {
        element.socket.emit('DiceRolled',
          JSON.stringify({ RoomId: Data.RoomId, UserId: Data.UserId, DiceResult: '', UserPosition: ActiveRooms[RoomIndex].Players[UserIndex].Position }));
      });
      ActiveRooms[RoomIndex].Turn += 1;
      if (ActiveRooms[RoomIndex].Turn > ActiveRooms[RoomIndex].Players.length - 1) {
        ActiveRooms[RoomIndex].Turn = 0;
      }
    }
    if (ActiveRooms[RoomIndex].Players[UserIndex].Position >= UsableHomes) {
      ActiveRooms[RoomIndex].Players.forEach((element) => {
        if (element.Id === Data.UserId) {
          element.socket.emit('Win');
        } else {
          element.socket.emit('Lose');
        }
      });
      return closeRoom(ActiveRooms[RoomIndex]);
    }
    ActiveRooms[RoomIndex].Players[UserIndex].QSheld = false;
    ActiveRooms[RoomIndex].Players.forEach((element) => {
      element.socket.emit('Turn', JSON.stringify({ RoomId: Data.RoomId, Turn: ActiveRooms[RoomIndex].Turn }));
    });
  } else if (itsUserTurn && !ActiveRooms[RoomIndex].Players[UserIndex].QSheld) {
    RunDice(socket, Data);
  }
}

function closeRoom(Room) {
  Room.Players.forEach((player) => {
    ids.splice(player.Id, 1);
  });
  ActiveRooms.splice(ActiveRooms.indexOf(Room), 1);
}

module.exports = (io) => {
  io.on('connection', (socket) => {
    socket.on('GenerateRoom', (Data) => { GenerateRoom(socket, Data); });
    socket.on('disconnect', () => { reconnect(socket); });
    socket.on('Reconnector', (Data) => { Updater(socket, Data); });
    socket.on('RegisterUser', (Data) => { verifyer(socket, LogUserIn, Data, false); });
    socket.on('RegisterUserName', (Data) => { verifyer(socket, RegisterUserName, Data); });
    socket.on('RunDice', (Data) => { verifyer(socket, RunDice, Data); });
    socket.on('QuestionResponse', (Data) => { verifyer(socket, QR, Data); });
  });
};
