var naPlugin = require('../index');
var nemo = {};
///Users/medelman/.nvm/current/bin/appium
var appiumPath = process.env.APPIUM_PATH;

naPlugin.setup(appiumPath, nemo, function (err, out) {
    if (err) {
        return console.error(err);
    }
    setTimeout(function () {
        nemo.appium && nemo.appium.process && nemo.appium.process.kill();
        console.log('things seem fine but somebody should write better unit tests');
    }, 1000);
});