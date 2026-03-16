export interface EncryptResult {
  encryptedData: string  // base64
  iv: string            // base64 (12 bytes)
  authTag: string       // base64 (16 bytes, extracted from GCM output)
}

export interface CryptoKey {
  key: globalThis.CryptoKey
}
