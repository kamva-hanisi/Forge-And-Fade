import Hero from "../../components/Hero/Hero";
import ServicesSection from "../../components/ServicesSection/ServicesSection";
import AboutSection from "../../components/AboutSection/AboutSection";
import BarbersSection from "../../components/BarbersSection/BarbersSection";
import WelcomeModal from "../../components/WelcomeModal/WelcomeModal";

const Home = () => {
  return (
    <main>
      <WelcomeModal />
      <Hero />
      <ServicesSection />
      <AboutSection />
      <BarbersSection />
    </main>
  );
};

export default Home;
