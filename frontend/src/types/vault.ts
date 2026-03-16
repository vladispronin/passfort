export type ItemType = 'login' | 'note' | 'card' | 'identity'

export interface Vault {
  id: string
  name: string
  createdAt: string
  updatedAt: string
}

export interface VaultItem {
  id: string
  encryptedData: string
  iv: string
  authTag: string
  itemType: ItemType
  titleHint: string
  isFavorite: boolean
  categoryId: string | null
  createdAt: string
  updatedAt: string
}

export interface DecryptedVaultItem extends Omit<VaultItem, 'encryptedData' | 'iv' | 'authTag'> {
  data: DecryptedItemData
}

export interface DecryptedItemData {
  username?: string
  password?: string
  url?: string
  notes?: string
  cardNumber?: string
  cardHolder?: string
  expiryDate?: string
  cvv?: string
  [key: string]: string | undefined
}

export interface CreateVaultItemPayload {
  encryptedData: string
  iv: string
  authTag: string
  itemType: ItemType
  titleHint: string
  categoryId?: string | null
  isFavorite?: boolean
}
