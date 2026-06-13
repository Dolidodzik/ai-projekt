export interface AnnouncementListItem {
  id: number
  title: string
  published_at: string | null
}

export interface AnnouncementDetail extends AnnouncementListItem {
  content: string
}
