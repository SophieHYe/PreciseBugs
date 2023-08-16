const exec = require('child_process').exec;
const fs = require('fs');
const path = require('path');

module.exports = async function (bin, options) {
    return new Promise(async (resolve, reject) => {
        const binDir = `${process.cwd()}/node_modules/.bin`;
        const cmd = path.join(binDir, bin);

        if (!cmd.startsWith(binDir)) {
            reject(new Error(`${cmd} within the expected directory`));
            return;
        }

        try {
            await fs.access(cmd, fs.constants.X_OK);
        } catch (err) {
            reject(new Error(`${cmd} is not accessible: ${err.message}`));
            return;
        }

        console.log(`Running \`${cmd}\``);

        const theProcess = exec(cmd, options, (error, stdout, stderr) => {
            if (stderr) {
                reject(error);
                return;
            }

            resolve(stdout);
        });

        theProcess.stdout.on('data', (data) => {
            process.stdout.write(data.toString());
        });

        theProcess.stderr.on('data', (data) => {
            process.stdout.write(data.toString());
        });
    });
};
