require ('hazardous')  // makes this module work when it's unpacked from the app.asar package when the app is packed with electron-build
// it overloads path.join to change asar.app to asar.app.unpacked
const path = require('path')
const { exec, execSync } = require('child_process')
const JSON5 = require('json5')

// The paths are defined here so that hazardous can work it's magic on the paths
const macFocusWindow = path.join(__dirname, 'mac', 'setWindowFocus.applescript')

const sendTextToWindowWithId = path.join(__dirname, 'linux', 'sendTextToWindowWithId.sh')
const macFocusAndSendKeys = path.join(__dirname, 'mac', 'focusAndSendKeysAndEnter.applescript')
const winSendKeysToWindowName = path.join(__dirname, 'windows', 'sendKeys.bat')

const linuxGetWindowList = path.join(__dirname, 'linux', 'getWindowList.sh')
const macGetWindowList = path.join(__dirname, 'mac', 'getWindowList.applescript')
const winGetWindowList = path.join(__dirname, 'windows', 'listOpenWindows.bat')

/**
 * Focuses the first window of the process with the PID given
 * @param {integer} id PID to use to find the application window
 * @param {function} callback callback, get error and message as parameters
 */
const focusWindow = (id, callback) => {
  const emptyCallBack = () => {}
  callback = callback || emptyCallBack
  if ( process.platform === 'darwin' ) {
    exec(`osascript "${macFocusWindow}" ${id}`, (error, stdout, stderr) => {
      if (error) {
        callback(error, null)
        return
      }
      if (stderr) {
        callback(stderr, null)
        return
      }
      callback(null, stdout)
    })
  } else if ( process.platform === 'win32' ) {
    // TODO: add windows support
    callback('Windows isn\'t supported yet', null)
  } else if ( process.platform === 'linux' ) {
    // TODO: add Linux support
    callback('Linux isn\'t supported yet', null)
  } else {
    callback('Platform not suported', null)
  }
  
}

/**
 * Focuses the first window of the PID given, then sends the cahracters in the keys string to the focused window by emulating the keyboard. 
 * Optionally, can focus back to the original application.
 * Optionally has a callback that gets (error, message) parameters for the error message (if any) and any output of the script
 * @param {integer} id PID of the pcoess to focus, or on linux and windows this would be the windowID
 * @param {string} keys string to send to the window
 * @param {*} [param2] optional parameters:
 *  - resetFocus: (default false) if set to true, will reset the focus to the original focus after sending the keys
 *  - pressEnterOnceDone: (default true) if set to true, will press enter once the keys have been sent
 * @returns {Promise} output of the script
 */
const sendKeys = (id, keys, {resetFocus = false, pressEnterOnceDone = true} = {}) => {
  let execPromise = new Promise((resolve, reject) => {
    keys = keys.replace('"', '\\"')

    if ( process.platform === 'darwin' ) {
      exec(`osascript "${macFocusAndSendKeys}" ${id} "${keys}" ${resetFocus} ${pressEnterOnceDone}`, (error, stdout, stderr) => {
        if (error) reject(error)
        if (stderr) reject(stderr)
        resolve(stdout)
      })

    } else if ( process.platform === 'win32' ) {
      // TODO: add option to reset focus on windows
      const windowTitle = id
      if (pressEnterOnceDone) {
        keys = keys + '~'
      }

      exec(`${winSendKeysToWindowName} "${windowTitle}" "${keys}"`, (error, stdout, stderr) => {
        if (error) reject(error)
        if (stderr) reject(stderr)
        resolve(stdout)
      })
      
    } else if ( process.platform === 'linux' ) {
      // TODO: add option to reset focus on linux
      // TODO: add option to not press enter once keys have been sent
      const windowID = id // although the function calls it pid, iin this case it's a windowID
      exec(`${sendTextToWindowWithId} ${windowID} "${keys}"`, (error, stdout, stderr) => {
        if (error) reject(error)
        if (stderr) reject(stderr)
        resolve(stdout)
      })

    } else {
      reject('Platform not suported')
    }
  })

  return execPromise
}

/**
 * Gets the list of open windows and returns it as an array of window objects.
 * On Linux each object has a id, user and title attributes
 * - id is the window id (linux)
 * - user is the user that owns the process running the window (linux)
 * - title is the title of the window
 * 
 * On mac, the object will contain
 * - processName is the name of the process owning the windows
 * - id identifier of the process
 * - windows[] and array of strings for the title of each window this process owns
 * 
 * In all cases, the ID can be used in sendKeys()
 * @return {Promise} array of window list Object
 */
const getWindowList = () => {
  return new Promise((resolve, reject) => {

    // Linux
    if (process.platform === 'linux') {
      exec(linuxGetWindowList, (error, stdout, stderr) => {
        if (error) reject(error)
        if (stderr) reject(stderr)
        windowStrings = stdout.split('\n')
        windowList = []
  
        windowStrings.forEach((windowString) => {
          let windowTitle = windowString.split(' ').slice(4).join(' ')
          windowObject = {id: windowString.split(' ')[0], user: windowString.split(' ')[3], title: windowTitle}
          windowList.push(windowObject)
        })
        resolve(windowList)
      })

    // MacOS
    } else if (process.platform === 'darwin'){
      exec(`osascript '${macGetWindowList}'`, (error, stdout, stderr) => {
        if (error) {
          reject(error)
        }
        if (stderr) {
          // in this case, the script outputs to stderr for some reason
          // so any stderr isn't an error
          // We check if the first character is "{", if it isn't
          // then it's an error message
          if (stderr.charAt(0) !== '{') {
            reject(stderr)
          } else {
            let winList = JSON5.parse(stderr).data
            resolve(winList)
          }

        } else {
          let winList = JSON5.parse(stdout).data
          resolve(winList)
        }
      })

    // Windows
    } else if (process.platform === 'win32') {

      exec(`${winGetWindowList}`, (error, stdout, stderr) => {
        if (error) {
          reject(error)
        }
        if (stderr) {
          reject(stderr)

        } else {
          // sort the output into an array and remove unecessary output
          let winList = stdout.split('\r\n').slice(2)
          winList = winList.filter(window => {
            if (window === '' || window === ' ') {
              return false
            } else {
              return true
            }
          })

          // remove extra whitespace
          winList = winList.map(win => {
            return win.trim()
          })

          resolve(winList)
        }
      })

    // Other
    } else {
      reject('platform not supported yet')
    }
  })
}

module.exports = {
  focusWindow: focusWindow,
  sendKeys: sendKeys,
  getWindowList: getWindowList,
}