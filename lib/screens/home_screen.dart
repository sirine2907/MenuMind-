import 'package:autrex/constants/color.dart';
import 'package:autrex/models/category.dart';
import 'package:autrex/screens/about_autris.dart';
import 'package:autrex/screens/atex_information.dart';
import 'package:autrex/screens/contact_us.dart';
import 'package:autrex/screens/favorites_screen.dart';
import 'package:autrex/screens/poster_guide.dart';
import 'package:autrex/widgets/circle_button.dart';
import 'package:autrex/widgets/search_textField.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;

  static final List<Widget> _widgetOptions = <Widget>[
    HomeContent(),
    Contact(),
    FavoritesScreen(),
    AboutAutrisScreen(),
  ];

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        body: Center(
          child: _widgetOptions.elementAt(_selectedIndex),
        ),
      ),
    );
  }
}

class HomeContent extends StatelessWidget {
  const HomeContent({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Column(
      children: const [
        CustomAppBar(),
        Body(),
      ],
    );
  }
}

class Body extends StatelessWidget {
  const Body({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 10, left: 20, right: 20),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  "Explore Categories",
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
                TextButton(
                  onPressed: () {},
                  child: Text(
                    "See All",
                    style: Theme.of(context)
                        .textTheme
                        .bodyMedium
                        ?.copyWith(color: kPrimaryColor),
                  ),
                )
              ],
            ),
          ),
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(
                horizontal: 20,
                vertical: 8,
              ),
              itemCount: categoryList.length,
              itemBuilder: (context, index) {
                return CategoryButton(
                  category: categoryList[index],
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class CategoryButton extends StatelessWidget {
  final Category category;
  const CategoryButton({
    Key? key,
    required this.category,
  }) : super(key: key);

  void navigateToCategoryScreen(BuildContext context, Category category) {
    switch (category.name) {
      case 'Contact Us':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => Contact(),
          ),
        );
        break;
      case 'Favorites':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => FavoritesScreen(),
          ),
        );
        break;
      case 'About us':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => AboutAutrisScreen(),
          ),
        );
        break;
      case 'ATEX Information':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => ATEXInformationScreen(),
          ),
        );
        break;
      case 'Poster Guide':
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => PosterScreen(), // Navigate to Poster Guide Screen
          ),
        );
        break;
      
       
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: ElevatedButton(
        onPressed: () => navigateToCategoryScreen(context, category),
        style: ElevatedButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          shadowColor: Colors.black.withOpacity(0.1),
          elevation: 5,
        ),
        child: Row(
          children: [
            Image.asset(
              category.thumbnail,
              height: 24, // Smaller icon size
              width: 24,  // Smaller icon size
            ),
            const SizedBox(width: 16),
            Text(
              category.name,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class CustomAppBar extends StatelessWidget {
  const CustomAppBar({
    Key? key,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.only(top: 50, left: 20, right: 20),
      height: 200,
      width: double.infinity,
      decoration: const BoxDecoration(
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(20),
          bottomRight: Radius.circular(20),
        ),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          stops: [0.1, 0.5],
          colors: [
            Color(0xffFFC850),
            Color(0xffEB874A),
          ],
        ),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                "Hello,\nWelcome to AUTREX",
                style: Theme.of(context).textTheme.titleLarge?.copyWith(color: Colors.white),
              ),
              CircleButton(
                icon: Icons.notifications,
                onPressed: () {},
              ),
            ],
          ),
          const SizedBox(
            height: 20,
          ),
          const SearchTextField()
        ],
      ),
    );
  }
}
