import type { Settings } from '../types'
import { GatingSection } from './GatingSection'
import { PricingSection } from './PricingSection'
import { WalletSection } from './WalletSection'

interface Props {
  settings: Settings
  onSettingsChange: (s: Settings) => void
}

export function SettingsTab({ settings, onSettingsChange }: Props) {
  return (
    <>
      <GatingSection settings={settings} onSettingsChange={onSettingsChange} />
      <PricingSection settings={settings} onSettingsChange={onSettingsChange} />
      <WalletSection settings={settings} onSettingsChange={onSettingsChange} />
    </>
  )
}
