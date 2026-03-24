export interface ReEncryptedItem {
  id: string
  encryptedData: string
  iv: string
  authTag: string
}

export interface ChangeMasterPasswordPayload {
  currentMasterPasswordHash: string
  newMasterPasswordHash: string
  newSalt: string
  newKdfParams: {
    algorithm: string
    iterations: number
    hash: string
    keyLength: number
  }
  items: ReEncryptedItem[]
}
