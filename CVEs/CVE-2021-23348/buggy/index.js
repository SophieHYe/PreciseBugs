const exec = require('./exec');
const listProcessesOnPort = module.exports.listProcessesOnPort = async port => {
	try {
		const result = (await exec(`lsof -i :${port}`)).output.split('\n');
		const headers = result.shift().split(' ').filter(item => !!item.trim() && item.trim() !== "").map(item => item.toLowerCase());
		return result.filter(item => !!item.trim() && item.trim() !== "").reduce((accumulator, currentValue) => {
			accumulator.push(currentValue.split(' ').filter(item => !!item.trim() && item.trim() !== "").reduce((accumulator, currentValue, index) => {
				if (index > headers.length - 1) {
					accumulator[headers[headers.length - 1]] = (!!accumulator[headers[headers.length - 1]].trim() && accumulator[headers[headers.length - 1]].trim() !== "") ? `${accumulator[headers[headers.length - 1]]} ${currentValue}` : currentValue;
				} else {
					accumulator[headers[index]] = currentValue;
				}
				return accumulator;
			}, {}));
			return accumulator;
		}, []);
	} catch (e) {
		console.error(e);
	}
};
const killProcess = module.exports.killProcess = async pid => {
	try {
		await exec(`kill ${pid}`);
		return true;
	} catch (e) {
		return false;
	}
};
const killAllProcessesOnPort = module.exports.killAllProcessesOnPort = async port => {
	try {
		const processesOnPort = await listProcessesOnPort(port);
		const killProcessResult = processesOnPort.map(theProcess => {
			const success = killProcess(theProcess.pid);
			return {pid: theProcess.pid, success};
		});
		return killProcessResult;
	} catch (e) {
		console.log(e);
	}
};
