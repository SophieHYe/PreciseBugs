const main = require('./index');

async function task() {
	const argv = [...process.argv];
	const port = process.argv.pop();
	const command = process.argv.pop();

	let result;
	switch (command) {
		case "kill":
			result = await main.killAllProcessesOnPort(port);
			console.log(result.filter(item => !item.success).map(item => `Failed to kill process ${item.pid}`).join('\n'));
			break;
		case "list":
			result = await main.listProcessesOnPort(port);
			console.log(result);
			break;
		default:
			console.error("Command not found");
	}
}
task();
