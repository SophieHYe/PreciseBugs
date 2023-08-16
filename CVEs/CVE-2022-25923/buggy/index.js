const exec = require('child_process').exec;

module.exports = async function (bin, options) {
    return new Promise((resolve, reject) => {
        const cmd = `${process.cwd()}/node_modules/.bin/${bin}`;

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
