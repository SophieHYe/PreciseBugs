var naPlugin = require('../index');
var nemo = {};
///Users/medelman/.nvm/current/bin/appium
var appiumPath ='touch HACKED && ~/bin/appium';

naPlugin.setup(appiumPath, nemo, function (err, out) {
    if (err) {
        console.log('You want to see this error. It means the setup function is validating the appium path for potential OS commands');
        return console.error(err);
    }
    setTimeout(function () {
        nemo.appium && nemo.appium.process && nemo.appium.process.kill();
        console.log('things seem fine but somebody should write better unit tests');
    }, 1000);
});