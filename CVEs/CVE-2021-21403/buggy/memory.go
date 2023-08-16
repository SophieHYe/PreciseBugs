package account

import "github.com/kongchuanhujiao/server/internal/app/datahub/internal/memory"

func GetCode(id string) string {
	return memory.Code[id]
}

func WriteCode(id string, code string) {
	memory.Code[id] = code
}

func DeleteCode(id string) {
	delete(memory.Code, id)
}
